<?php

namespace App\Services\Bot;

use App\Models\Brand;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\AI\AiGatewayService;
use Illuminate\Support\Facades\Log;

/**
 * Main orchestrator for the Vumbi conversational bot.
 *
 * AI backend priority:
 *  1. BotPrismService  — Prism PHP + Fireworks (Meta Llama 3.3 70B)
 *                        Used when prism-php/prism is installed and
 *                        PRISM_BOT_PROVIDER is set in .env
 *  2. AiGatewayService — Raw HTTP fallback (Gemini / OpenAI / etc.)
 *                        Used when Prism is not installed
 *
 * Flow:
 *  1. Resolve or create ChatSession for this user + brand
 *  2. Save the user's message
 *  3. Build system prompt via BotContextBuilder
 *  4. Append recent conversation history
 *  5. Call AI (Prism preferred, gateway fallback)
 *  6. Detect structured action JSON in the AI response
 *  7. If action → BotActionHandler fetches real data → wrap conversationally
 *  8. Save the assistant reply
 *  9. Return reply + metadata
 */
class BotService
{
    public function __construct(
        private BotContextBuilder $contextBuilder,
        private BotActionHandler  $actionHandler,
        private AiGatewayService  $aiGateway,
    ) {}

    /**
     * Send a message from a user and receive the bot's reply.
     *
     * @return array{reply: string, session_id: int, action_type: string|null, tokens_used: int}
     */
    public function chat(string $userMessage, Brand $brand, User $user): array
    {
        $session      = $this->resolveSession($brand, $user);
        $systemPrompt = $this->contextBuilder->build($brand, $user);
        $history      = $session->recentHistory(20);

        ChatMessage::create([
            'session_id'  => $session->id,
            'role'        => 'user',
            'content'     => $userMessage,
            'tokens_used' => 0,
        ]);

        [$rawReply, $tokensUsed] = $this->callAI($systemPrompt, $userMessage, $history);

        if ($rawReply === null) {
            $reply = "I'm having trouble connecting right now. Please try again in a moment.";
            $this->saveAssistantMessage($session, $reply, null, 0);
            return ['reply' => $reply, 'session_id' => $session->id, 'action_type' => null, 'tokens_used' => 0];
        }

        $actionType = null;
        $trimmed    = trim($rawReply);

        if (str_starts_with($trimmed, '{') && $this->isActionResponse($trimmed)) {
            [$reply, $actionType, $tokensUsed] = $this->handleAction($trimmed, $brand, $history, $systemPrompt, $tokensUsed);
        } else {
            $reply = $rawReply;
        }

        $this->saveAssistantMessage($session, $reply, $actionType, $tokensUsed);

        $session->last_active_at = now();
        $session->save();

        return [
            'reply'       => $reply,
            'session_id'  => $session->id,
            'action_type' => $actionType,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * Onboarding mode — no brand yet, guide the user through setup.
     */
    public function onboardingChat(string $userMessage, User $user): array
    {
        $session = ChatSession::firstOrCreate(
            ['brand_id' => 0, 'user_id' => $user->id, 'mode' => 'onboarding'],
            ['last_active_at' => now()]
        );

        $systemPrompt = $this->contextBuilder->buildOnboardingPrompt($user);
        $history      = $session->recentHistory(10);

        ChatMessage::create([
            'session_id'  => $session->id,
            'role'        => 'user',
            'content'     => $userMessage,
            'tokens_used' => 0,
        ]);

        [$reply, $tokensUsed] = $this->callAI($systemPrompt, $userMessage, $history);

        $reply ??= "I'm having trouble connecting right now. Please try again.";

        $this->saveAssistantMessage($session, $reply, null, $tokensUsed);

        $session->last_active_at = now();
        $session->save();

        return [
            'reply'       => $reply,
            'session_id'  => $session->id,
            'action_type' => null,
            'tokens_used' => $tokensUsed,
        ];
    }

    // ── AI dispatch ─────────────────────────────────────────────────────────

    /**
     * Call the best available AI backend.
     * Returns [content|null, tokens_used].
     *
     * @return array{string|null, int}
     */
    private function callAI(string $systemPrompt, string $userMessage, array $history): array
    {
        // Prefer Prism (Fireworks/Llama) when installed
        if ($this->prismAvailable()) {
            try {
                /** @var BotPrismService $prism */
                $prism  = app(BotPrismService::class);
                $result = $prism->generate($systemPrompt, $userMessage, $history);
                return [$result['content'], $result['tokens_used']];
            } catch (\Throwable $e) {
                Log::warning('BotService: Prism failed, falling back to gateway', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: raw gateway (Gemini / OpenAI / etc.)
        $result = $this->aiGateway->generate([
            'system_prompt' => $systemPrompt,
            'user_prompt'   => $userMessage,
            'history'       => $history,
        ]);

        if (!$result['success']) {
            return [null, 0];
        }

        return [$result['content'], $result['tokens_used'] ?? 0];
    }

    /**
     * Wrap action data conversationally using the best available backend.
     */
    private function wrapActionData(string $dataText, string $systemPrompt, array $history): array
    {
        if ($this->prismAvailable()) {
            try {
                /** @var BotPrismService $prism */
                $prism  = app(BotPrismService::class);
                $result = $prism->wrapActionData($dataText);
                return [$result['content'], $result['tokens_used']];
            } catch (\Throwable $e) {
                Log::warning('BotService: Prism wrapActionData failed', ['error' => $e->getMessage()]);
            }
        }

        $result = $this->aiGateway->generate([
            'system_prompt' => 'You are the Vumbi AI assistant. Summarise the provided data conversationally. Be concise and actionable.',
            'user_prompt'   => $dataText,
        ]);

        return [
            $result['success'] ? $result['content'] : null,
            $result['tokens_used'] ?? 0,
        ];
    }

    /**
     * Check if prism-php/prism is installed.
     */
    private function prismAvailable(): bool
    {
        return class_exists(\Prism\Prism\Facades\Prism::class);
    }

    // ── Action handling ──────────────────────────────────────────────────────

    /**
     * @return array{string, string|null, int}  [reply, action_type, tokens_used]
     */
    private function handleAction(
        string $json,
        Brand  $brand,
        array  $history,
        string $systemPrompt,
        int    $existingTokens
    ): array {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $action  = $decoded['action'];
            $params  = $decoded['params'] ?? [];

            $result = $this->actionHandler->handle($action, $params, $brand);

            $wrapPrompt = "Here is the data you requested:\n\n{$result['data']}\n\n"
                . "Write a brief, conversational response (2–4 sentences) based on this data. "
                . "Plain text only — no JSON.";

            [$wrapped, $wrapTokens] = $this->wrapActionData($wrapPrompt, $systemPrompt, $history);

            $totalTokens = $existingTokens + $wrapTokens;
            $reply       = $wrapped ?? $result['data'];

            return [$reply, $result['action_type'], $totalTokens];

        } catch (\Throwable $e) {
            Log::error('BotService: action handling failed', [
                'brand_id' => $brand->id,
                'error'    => $e->getMessage(),
            ]);
            return ["I tried to fetch that data but ran into an issue. Please try again.", null, $existingTokens];
        }
    }

    // ── Persistence ──────────────────────────────────────────────────────────

    private function resolveSession(Brand $brand, User $user): ChatSession
    {
        return ChatSession::firstOrCreate(
            ['brand_id' => $brand->id, 'user_id' => $user->id, 'mode' => 'assistant'],
            ['last_active_at' => now()]
        );
    }

    private function isActionResponse(string $json): bool
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return isset($decoded['action']) && is_string($decoded['action']);
        } catch (\JsonException) {
            return false;
        }
    }

    private function saveAssistantMessage(
        ChatSession $session,
        string      $reply,
        ?string     $actionType,
        int         $tokensUsed
    ): void {
        ChatMessage::create([
            'session_id'  => $session->id,
            'role'        => 'assistant',
            'content'     => $reply,
            'action_type' => $actionType,
            'tokens_used' => $tokensUsed,
        ]);
    }
}
