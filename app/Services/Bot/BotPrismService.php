<?php

namespace App\Services\Bot;

use App\Models\Brand;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

/**
 * Prism-based bot implementation.
 *
 * Uses prism-php/prism with the Fireworks provider (Meta Llama 3.3 70B).
 * Drop-in replacement for the raw HTTP path in BotService — same inputs,
 * same return shape.
 *
 * Install: composer require prism-php/prism
 * Publish: php artisan vendor:publish --tag=prism-config
 *
 * config/prism.php providers block:
 *   'fireworks' => [
 *       'driver'  => 'openai',  // Fireworks is OpenAI-compatible
 *       'url'     => env('FIREWORKS_URL', 'https://api.fireworks.ai/inference/v1'),
 *       'api_key' => env('FIREWORKS_API_KEY', ''),
 *   ],
 *
 * .env:
 *   PRISM_BOT_PROVIDER=fireworks
 *   PRISM_BOT_MODEL=accounts/fireworks/models/llama-v3p3-70b-instruct
 *   FIREWORKS_API_KEY=your_key_here
 *   FIREWORKS_URL=https://api.fireworks.ai/inference/v1
 */
class BotPrismService
{
    /**
     * Generate a bot reply using Prism + Fireworks/Llama.
     *
     * @param  string       $systemPrompt  Built by BotContextBuilder
     * @param  string       $userMessage   The current user turn
     * @param  array        $history       [{role: user|assistant, content: string}]
     * @return array{content: string, tokens_used: int, model: string}
     */
    public function generate(
        string $systemPrompt,
        string $userMessage,
        array  $history = []
    ): array {
        $provider = env('PRISM_BOT_PROVIDER', 'fireworks');
        $model    = env('PRISM_BOT_MODEL', 'accounts/fireworks/models/llama-v3p3-70b-instruct');

        // Build message history for Prism
        $messages = [];
        foreach ($history as $entry) {
            $messages[] = $entry['role'] === 'assistant'
                ? new AssistantMessage($entry['content'])
                : new UserMessage($entry['content']);
        }
        // Append current user turn
        $messages[] = new UserMessage($userMessage);

        $response = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt($systemPrompt)
            ->withMessages($messages)
            ->withMaxTokens(2048)
            ->generate();

        return [
            'content'     => $response->text,
            'tokens_used' => $response->usage->totalTokens ?? 0,
            'model'       => $model,
        ];
    }

    /**
     * Generate a short follow-up to wrap action data conversationally.
     */
    public function wrapActionData(string $dataText): array
    {
        $provider = env('PRISM_BOT_PROVIDER', 'fireworks');
        $model    = env('PRISM_BOT_MODEL', 'accounts/fireworks/models/llama-v3p3-70b-instruct');

        $response = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt(
                'You are a concise business assistant. Given data, write 2–3 sentences ' .
                'that summarise it conversationally and suggest the most important next step. ' .
                'Plain text only — no JSON, no markdown headers.'
            )
            ->withPrompt($dataText)
            ->withMaxTokens(300)
            ->generate();

        return [
            'content'     => $response->text,
            'tokens_used' => $response->usage->totalTokens ?? 0,
        ];
    }
}
