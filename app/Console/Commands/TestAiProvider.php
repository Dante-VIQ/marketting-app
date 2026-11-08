<?php

namespace App\Console\Commands;

use App\Services\AI\AiGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * TestAiProvider — smoke-tests any AI provider from the command line.
 *
 * Usage (on the server):
 *   php artisan ai:test groq
 *   php artisan ai:test mistral
 *   php artisan ai:test fireworks
 *   php artisan ai:test gemini
 *
 * Runs two checks:
 *   1. Raw cURL → shows exact HTTP status + body before Laravel Http touches it
 *   2. AiGatewayService::generate() → exercises the full stack
 */
class TestAiProvider extends Command
{
    protected $signature   = 'ai:test {provider : Provider name: groq, mistral, fireworks, gemini, openai, anthropic}';
    protected $description = 'Smoke-test an AI provider — shows raw HTTP status, key presence, and a live generate() call.';

    public function handle(): int
    {
        $provider = strtolower(trim($this->argument('provider')));
        $this->info("Testing provider: {$provider}");
        $this->newLine();

        // ── 1. Env var presence ───────────────────────────────────────────────
        $keyEnvMap = [
            'groq'      => 'GROQ_API_KEY',
            'mistral'   => 'MISTRAL_API_KEY',
            'fireworks' => 'FIREWORKS_API_KEY',
            'gemini'    => 'GEMINI_API_KEY',
            'openai'    => 'OPENAI_API_KEY',
            'anthropic' => 'ANTHROPIC_API_KEY',
            'baseten'   => 'BASETEN_API_KEY',
        ];

        $modelEnvMap = [
            'groq'      => ['GROQ_MODEL',      'llama-3.3-70b-versatile'],
            'mistral'   => ['MISTRAL_MODEL',    'mistral-large-latest'],
            'fireworks' => ['FIREWORKS_MODEL',  'accounts/fireworks/models/llama-v3p3-70b-instruct'],
            'gemini'    => ['GEMINI_MODEL',     'gemini-2.0-flash'],
            'openai'    => ['OPENAI_MODEL',     'gpt-4'],
            'anthropic' => ['ANTHROPIC_MODEL',  'claude-3-opus-20240229'],
        ];

        $keyEnv = $keyEnvMap[$provider] ?? strtoupper($provider) . '_API_KEY';
        $apiKey = env($keyEnv) ?: getenv($keyEnv) ?: '';

        if (empty($apiKey)) {
            $this->error("✗ {$keyEnv} is not set or empty in .env");
            return 1;
        }
        $this->line("✓ {$keyEnv} is set (length=" . strlen($apiKey) . ", prefix=" . substr($apiKey, 0, 8) . "…)");

        [$modelEnv, $modelDefault] = $modelEnvMap[$provider] ?? [strtoupper($provider) . '_MODEL', 'unknown'];
        $model = env($modelEnv) ?: getenv($modelEnv) ?: $modelDefault;
        $this->line("  Model: {$model}");
        $this->newLine();

        // ── 2. Raw HTTP probe (bypasses Laravel Http wrapper) ─────────────────
        $endpointMap = [
            'groq'      => 'https://api.groq.com/openai/v1/chat/completions',
            'mistral'   => 'https://api.mistral.ai/v1/chat/completions',
            'fireworks' => 'https://api.fireworks.ai/inference/v1/chat/completions',
            'openai'    => 'https://api.openai.com/v1/chat/completions',
            'baseten'   => 'https://inference.baseten.co/v1/chat/completions',
            // Gemini & Anthropic have different formats — skip raw probe for them
        ];

        if (isset($endpointMap[$provider])) {
            $endpoint = $endpointMap[$provider];
            $this->line("── Raw cURL probe ──────────────────────────────────────");
            $this->line("   Endpoint: {$endpoint}");

            $payload = json_encode([
                'model'      => $model,
                'messages'   => [['role' => 'user', 'content' => 'Reply with just the word: OK']],
                'max_tokens' => 5,
            ]);

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_VERBOSE        => false,
            ]);

            $body   = curl_exec($ch);
            $errno  = curl_errno($ch);
            $errmsg = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno) {
                $this->error("   cURL error #{$errno}: {$errmsg}");
                $this->warn("   → Network/proxy is blocking outbound HTTPS to this host.");
                $this->warn("   → This is a server-side network restriction, not a code bug.");
            } else {
                $this->line("   HTTP status: {$status}");
                $decoded = json_decode($body, true);
                if ($status === 200) {
                    $reply = $decoded['choices'][0]['message']['content'] ?? '(no content)';
                    $this->info("   ✓ Success! Reply: {$reply}");
                } else {
                    $this->error("   ✗ HTTP {$status}");
                    $this->line("   Body: " . substr($body, 0, 500));
                    if ($status === 401) $this->warn("   → API key is invalid or expired.");
                    if ($status === 403) $this->warn("   → API key lacks permission or account issue.");
                    if ($status === 404) $this->warn("   → Model '{$model}' may not exist for this key tier.");
                    if ($status === 422 || $status === 400) $this->warn("   → Bad request — check model name.");
                    if ($status === 429) $this->warn("   → Rate limit hit.");
                }
            }
            $this->newLine();
        }

        // ── 3. AiGatewayService::generate() ──────────────────────────────────
        $this->line("── AiGatewayService::generate() ────────────────────────");

        // Temporarily override AI_PROVIDER to the requested one
        putenv("AI_PROVIDER={$provider}");

        try {
            $gateway = new AiGatewayService($provider);

            $result = $gateway->generate([
                'system_prompt' => 'You are a test assistant.',
                'user_prompt'   => 'Reply with exactly the word: OK',
                'max_tokens'    => 10,
                'temperature'   => 0.0,
            ]);

            if ($result['success']) {
                $this->info("   ✓ generate() succeeded");
                $this->line("   Content   : " . trim($result['content']));
                $this->line("   Tokens    : " . ($result['tokens_used'] ?? '?'));
                $this->line("   Model     : " . ($result['model_used'] ?? '?'));
                $this->line("   Time (ms) : " . ($result['response_time_ms'] ?? '?'));
            } else {
                $this->error("   ✗ generate() failed: " . ($result['error'] ?? 'unknown error'));
            }
        } catch (\Throwable $e) {
            $this->error("   ✗ Exception: " . $e->getMessage());
        }

        return 0;
    }
}
