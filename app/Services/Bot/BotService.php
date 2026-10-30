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
 * Flow:
 *  1. Resolve or create ChatSession for this user + brand
 *  2. Save the user's message
 *  3. Build the system prompt via BotContextBuilder
 *  4. Append recent conversation history
 *  5. Call AiGatewayService::generate()
 *  6. Detect structured action JSON in the AI response
 *  7. If action → delegate to BotActionHandler, build final reply
 *  8. Save the assistant reply
 *  9. Return reply text + metadata
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
     * @return array{
     *     reply: string,
     *     session_id: int,
     *     action_type: string|null,
     *     tokens_used: int,
     * }
     */
    public function chat(string $userMessage, Brand $brand, User $user): array
    {
        // 1. Resolve session
        $session = $this->resolveSession($brand, $user);

        // 2. Save user message
        ChatMessage::create([
            'session_id'  => $session->id,
            'role'        => 'user',
            'content'     => $userMessage,
            'tokens_used' => 0,
        ]);

        // 3. Build system prompt
        $systemPrompt = $this->contextBuilder->build($brand, $user);

        // 4. Build conversation history (last 10 exchanges = up to 20 messages)
        $history = $session->recentHistory(20);

        // 5. Call AI
        $aiResult = $this->aiGateway->generate([
            'system_prompt' => $systemPrompt,
            'user_prompt'   => $userMessage,
            'history'       => $history,
        ]);

        if (!$aiResult['success']) {
            $reply = "I'm having trouble connecting right now. Please try again in a moment.";

            $this->saveAssistantMessage($session, $reply, null, 0);

            return [
                'reply'       => $reply,
                'session_id'  => $session->id,
                'action_type' => null,
                'tokens_used' => 0,
            ];
        }

        $rawReply    = $aiResult['content'];
        $tokensUsed  = $aiResult['tokens_used'] ?? 0;
        $actionType  = null;

        // 6. Detect structured action JSON
        $trimmed = trim($rawReply);
        if (str_starts_with($trimmed, '{') && $this->isActionResponse($trimmed)) {
            [$reply, $actionType, $tokensUsed] = $this->handleAction(
                $trimmed, $brand, $session, $tokensUsed
            );
        } else {
            $reply = $rawReply;
        }

        // 7. Save assistant reply
        $this->saveAssistantMessage($session, $reply, $actionType, $tokensUsed);

        // 8. Touch session
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
     * Send a message in onboarding mode (no brand yet).
     */
    public function onboardingChat(string $userMessage, User $user): array
    {
        // Use a synthetic brand-less session keyed by user ID
        $session = ChatSession::firstOrCreate(
            ['brand_id' => 0, 'user_id' => $user->id, 'mode' => 'onboarding'],
            ['last_active_at' => now()]
        );

        ChatMessage::create([
            'session_id'  => $session->id,
            'role'        => 'user',
            'content'     => $userMessage,
            'tokens_used' => 0,
        ]);

        $systemPrompt = $this->contextBuilder->buildOnboardingPrompt($user);
        $history      = $session->recentHistory(10);

        $aiResult = $this->aiGateway->generate([
            'system_prompt' => $systemPrompt,
            'user_prompt'   => $userMessage,
            'history'       => $history,
        ]);

        $reply      = $aiResult['success']
            ? $aiResult['content']
            : "I'm having trouble connecting right now. Please try again.";
        $tokensUsed = $aiResult['tokens_used'] ?? 0;

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

    // ── Private helpers ──────────────────────────────────────────────────────

    private function resolveSession(Brand $brand, User $user): ChatSession
    {
        return ChatSession::firstOrCreate(
            ['brand_id' => $brand->id, 'user_id' => $user->id, 'mode' => 'assistant'],
            ['last_active_at' => now()]
        );
    }

    /**
     * Determine whether a JSON string is a structured action request.
     */
    private function isActionResponse(string $json): bool
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return isset($decoded['action']) && is_string($decoded['action']);
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Execute a structured action and build the final reply.
     *
     * @return array{string, string|null, int}  [reply, action_type, tokens_used]
     */
    private function handleAction(
        string $json,
        Brand $brand,
        ChatSession $session,
        int $existingTokens
    ): array {
        try {
            $decoded     = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $action      = $decoded['action'];
            $params      = $decoded['params'] ?? [];
            $placeholder = $decoded['message'] ?? 'Fetching that for you…';

            // Execute the action
            $result = $this->actionHandler->handle($action, $params, $brand);

            // Build a follow-up prompt so the AI wraps the data in a conversational reply
            $followUpPrompt = "Here is the data you requested:\n\n{$result['data']}\n\nNow write a brief, conversational response (2–4 sentences) based on this data. Do NOT output JSON this time — plain conversational text only.";

            $followUp = $this->aiGateway->generate([
                'system_prompt' => "You are the Vumbi AI assistant. Summarise the provided data conversationally for the user. Be concise and actionable.",
                'user_prompt'   => $followUpPrompt,
            ]);

            $totalTokens = $existingTokens + ($followUp['tokens_used'] ?? 0);

            if ($followUp['success']) {
                return [$followUp['content'], $result['action_type'], $totalTokens];
            }

            // Fallback: return the raw formatted data
            return [$result['data'], $result['action_type'], $totalTokens];

        } catch (\Throwable $e) {
            Log::error('BotService: action handling failed', [
                'brand_id' => $brand->id,
                'error'    => $e->getMessage(),
            ]);
            return [
                "I tried to fetch that data but ran into an issue. Please try again.",
                null,
                $existingTokens,
            ];
        }
    }

    private function saveAssistantMessage(
        ChatSession $session,
        string $reply,
        ?string $actionType,
        int $tokensUsed
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
