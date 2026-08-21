<?php

namespace App\Services\AI;

use App\Models\Brand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiGatewayService
{
    protected string $provider;
    protected array $config;

    public function __construct()
    {
        $this->provider = env('AI_PROVIDER', 'gemini');
        $this->config = $this->getProviderConfig();
    }

    /**
     * Get provider configuration.
     */
    protected function getProviderConfig(): array
    {
        return match ($this->provider) {
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_MODEL', 'gpt-4'),
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
            ],
            'anthropic' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'model' => env('ANTHROPIC_MODEL', 'claude-3-opus-20240229'),
                'endpoint' => 'https://api.anthropic.com/v1/messages',
            ],
            'gemini' => [
                'api_key' => env('GEMINI_API_KEY'),
                'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
            ],
            'ollama' => [
                'model' => env('OLLAMA_MODEL', 'llama3.1'),
                'endpoint' => env('OLLAMA_ENDPOINT', 'http://localhost:11434/api/generate'),
            ],
            default => throw new \Exception("Unsupported AI provider: {$this->provider}"),
        };
    }

    /**
     * Send a prompt to the AI and get a response.
     */
public function generate(array $promptData): array
{
    $startTime = microtime(true);

    try {
        $response = match ($this->provider) {
            'openai' => $this->callOpenAI($promptData),
            'anthropic' => $this->callAnthropic($promptData),
            'gemini' => $this->callGeminiWithRetry($promptData), // Use retry version
            'ollama' => $this->callOllama($promptData),
            default => throw new \Exception("Unsupported AI provider: {$this->provider}"),
        };

        $responseTime = (microtime(true) - $startTime) * 1000;

        return [
            'success' => true,
            'content' => $response['content'],
            'tokens_used' => $response['tokens_used'] ?? 0,
            'model_used' => $this->config['model'],
            'provider' => $this->provider,
            'response_time_ms' => round($responseTime, 2),
            'raw_response' => $response['raw'] ?? null,
        ];
    } catch (\Exception $e) {
        Log::error('AI Gateway error', [
            'provider' => $this->provider,
            'error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'provider' => $this->provider,
        ];
    }
}

    /**
     * Call OpenAI API.
     */
    protected function callOpenAI(array $promptData): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
        ])->post($this->config['endpoint'], [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $promptData['system_prompt'] ?? ''],
                ['role' => 'user', 'content' => $promptData['user_prompt']],
            ],
            'temperature' => $promptData['temperature'] ?? 0.7,
            'max_tokens' => $promptData['max_tokens'] ?? 4096,
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'raw' => $data,
        ];
    }

    /**
     * Call Anthropic API.
     */
    protected function callAnthropic(array $promptData): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->config['api_key'],
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post($this->config['endpoint'], [
            'model' => $this->config['model'],
            'system' => $promptData['system_prompt'] ?? '',
            'messages' => [
                ['role' => 'user', 'content' => $promptData['user_prompt']],
            ],
            'temperature' => $promptData['temperature'] ?? 0.7,
            'max_tokens' => $promptData['max_tokens'] ?? 4096,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Anthropic API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'tokens_used' => $data['usage']['input_tokens'] + $data['usage']['output_tokens'],
            'raw' => $data,
        ];
    }

    /**
     * Call Gemini API.
     */
    protected function callGemini(array $promptData): array
    {
        $model = $this->config['model'];
        $apiKey = $this->config['api_key'];
        
        $endpoint = $this->config['endpoint'] . '/' . $model . ':generateContent?key=' . $apiKey;

        // Build the prompt
        $systemPrompt = $promptData['system_prompt'] ?? '';
        $userPrompt = $promptData['user_prompt'] ?? '';

        // Combine system and user prompts for Gemini
        $fullPrompt = $systemPrompt ? $systemPrompt . "\n\n" . $userPrompt : $userPrompt;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $promptData['temperature'] ?? 0.7,
                'maxOutputTokens' => $promptData['max_tokens'] ?? 4096,
                'topP' => 0.95,
                'topK' => 40,
            ],
        ];

        // If we need JSON response, add it to the prompt
        if (isset($promptData['response_format']) && $promptData['response_format'] === 'json') {
            $payload['contents'][0]['parts'][0]['text'] .= "\n\nReturn ONLY valid JSON. No markdown, no explanations outside JSON.";
        }

        $response = Http::timeout(120)->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new \Exception('Gemini API error: ' . $response->body());
        }

        $data = $response->json();

        // Extract content from Gemini response
        $content = '';
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $data['candidates'][0]['content']['parts'][0]['text'];
        }

        // Try to parse JSON if it's a JSON response
        if (isset($promptData['response_format']) && $promptData['response_format'] === 'json') {
            // Try to extract JSON from the response
            $jsonContent = $this->extractJsonFromResponse($content);
            if ($jsonContent) {
                $content = $jsonContent;
            }
        }

        return [
            'content' => $content,
            'tokens_used' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            'raw' => $data,
        ];
    }

    /**
     * Extract JSON from a response that might contain markdown.
     */
    protected function extractJsonFromResponse(string $response): ?string
    {
        // Try to find JSON between ```json and ```
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
            return $matches[1];
        }

        // Try to find JSON between ``` and ```
        if (preg_match('/```\s*([\s\S]*?)\s*```/', $response, $matches)) {
            return $matches[1];
        }

        // Try to find anything that looks like JSON
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Call Ollama API (local).
     */
    protected function callOllama(array $promptData): array
    {
        $payload = [
            'model' => $this->config['model'],
            'prompt' => $this->buildOllamaPrompt($promptData),
            'stream' => false,
            'format' => 'json',
            'options' => [
                'temperature' => $promptData['temperature'] ?? 0.7,
                'num_predict' => $promptData['max_tokens'] ?? 4096,
            ],
        ];

        $response = Http::timeout(120)->post($this->config['endpoint'], $payload);

        if (!$response->successful()) {
            throw new \Exception('Ollama API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['response'] ?? '',
            'tokens_used' => $data['eval_count'] ?? 0,
            'raw' => $data,
        ];
    }

    /**
     * Build prompt for Ollama (system + user combined).
     */
    protected function buildOllamaPrompt(array $promptData): string
    {
        $prompt = '';
        
        if (!empty($promptData['system_prompt'])) {
            $prompt .= "System: " . $promptData['system_prompt'] . "\n\n";
        }

        $prompt .= "User: " . $promptData['user_prompt'] . "\n\n";
        $prompt .= "Assistant:";

        return $prompt;
    }

    /**
     * Get the current provider.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Check if the AI service is available.
     */
    public function isAvailable(): bool
    {
        try {
            if ($this->provider === 'gemini') {
                $apiKey = $this->config['api_key'] ?? null;
                return !empty($apiKey);
            }

            if ($this->provider === 'ollama') {
                $response = Http::timeout(5)->get('http://localhost:11434/api/tags');
                return $response->successful();
            }
            
            return !empty($this->config['api_key']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
 * Call Gemini API with retry logic.
 */
protected function callGeminiWithRetry(array $promptData, int $maxRetries = 3): array
{
    $attempt = 0;
    $delay = 10; // seconds

    while ($attempt < $maxRetries) {
        try {
            $result = $this->callGemini($promptData);
            
            // If we got a quota error response, check if it's a retryable error
            if (isset($result['error']) && str_contains($result['error'], 'quota')) {
                throw new \Exception($result['error']);
            }
            
            return $result;
        } catch (\Exception $e) {
            $attempt++;
            
            // Check if it's a quota error
            if (str_contains($e->getMessage(), 'quota') || str_contains($e->getMessage(), 'RESOURCE_EXHAUSTED')) {
                if ($attempt < $maxRetries) {
                    $this->info("Gemini quota exceeded. Retrying in {$delay} seconds...");
                    sleep($delay);
                    $delay *= 2; // Exponential backoff
                    continue;
                }
            }
            
            throw $e;
        }
    }

    throw new \Exception("Max retries exceeded for Gemini API");
}
}