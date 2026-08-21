<?php

namespace App\Console\Commands;

use App\Services\AI\AiGatewayService;
use Illuminate\Console\Command;

class TestGeminiConnection extends Command
{
    protected $signature = 'ai:test-gemini';
    protected $description = 'Test the Gemini API connection';

    public function handle(AiGatewayService $aiGateway)
    {
        $this->info('Testing Gemini API connection...');
        $this->info('Provider: ' . $aiGateway->getProvider());
        $this->info('Available: ' . ($aiGateway->isAvailable() ? 'Yes' : 'No'));

        if (!$aiGateway->isAvailable()) {
            $this->error('Gemini API is not available. Check your API key.');
            return 1;
        }

        $this->info('Sending test prompt...');

        $response = $aiGateway->generate([
            'system_prompt' => 'You are a helpful assistant.',
            'user_prompt' => 'Say hello to Vumbi Ventures in 5 words or less.',
            'temperature' => 0.7,
            'max_tokens' => 100,
            'response_format' => 'json',
        ]);

        if (!$response['success']) {
            $this->error('Error: ' . $response['error']);
            return 1;
        }

        $this->info('Response: ' . $response['content']);
        $this->info('Model: ' . $response['model_used']);
        $this->info('Tokens: ' . $response['tokens_used']);
        $this->info('Response time: ' . $response['response_time_ms'] . 'ms');

        return 0;
    }
}