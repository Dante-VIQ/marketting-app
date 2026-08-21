<?php

namespace App\Services\Gateway;

use App\Models\Brand;
use App\Models\DomainRoutingRule;
use App\Models\GuardianIncident;
use App\Services\AI\AiGatewayService;
use Illuminate\Support\Facades\Log;

class DomainGatewayService
{
    protected AiGatewayService $aiGateway;

    public function __construct(AiGatewayService $aiGateway)
    {
        $this->aiGateway = $aiGateway;
    }

    /**
     * Route a request to the appropriate domain AI.
     */
    public function route(Brand $brand, string $intentType, array $data): array
    {
        $domainType = $brand->domain_type;

        // Get routing rule
        $rule = DomainRoutingRule::where('domain_type', $domainType)
            ->where('intent_type', $intentType)
            ->where('is_active', true)
            ->first();

        if (!$rule) {
            // Fallback to general rule
            $rule = DomainRoutingRule::where('domain_type', 'general')
                ->where('intent_type', $intentType)
                ->where('is_active', true)
                ->first();
        }

        if (!$rule) {
            Log::error('No routing rule found', [
                'domain_type' => $domainType,
                'intent_type' => $intentType,
            ]);
            return [
                'success' => false,
                'error' => 'No routing rule found for this domain and intent.',
            ];
        }

        // Load the prompt template
        $prompt = $this->loadPromptTemplate($rule->prompt_template_key, $domainType);

        if (!$prompt) {
            Log::error('Prompt template not found', [
                'key' => $rule->prompt_template_key,
                'domain_type' => $domainType,
            ]);
            return [
                'success' => false,
                'error' => 'Prompt template not found.',
            ];
        }

        // Prepare the prompt with data
        $systemPrompt = $this->preparePrompt($prompt['system'], $data, $brand);
        $userPrompt = $this->preparePrompt($prompt['user'], $data, $brand);

        // Call the AI
        $response = $this->aiGateway->generate([
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'temperature' => $data['temperature'] ?? 0.7,
            'max_tokens' => $data['max_tokens'] ?? 4096,
        ]);

        if (!$response['success']) {
            // Log incident
            $this->logIncident($brand, 'system_error', 'medium', $response['error'] ?? 'AI call failed');

            return [
                'success' => false,
                'error' => $response['error'] ?? 'AI call failed',
            ];
        }

        return [
            'success' => true,
            'content' => $response['content'],
            'provider' => $response['provider'],
            'model_used' => $response['model_used'],
            'tokens_used' => $response['tokens_used'],
            'response_time_ms' => $response['response_time_ms'],
            'rule_used' => $rule->toArray(),
        ];
    }

    /**
     * Load a prompt template.
     */
    protected function loadPromptTemplate(string $key, string $domainType): ?array
    {
        $paths = [
            "prompts/{$domainType}/{$key}.json",
            "prompts/general/{$key}.json",
            "prompts/{$key}.json",
        ];

        foreach ($paths as $path) {
            if (file_exists(resource_path($path))) {
                $content = file_get_contents(resource_path($path));
                return json_decode($content, true);
            }
        }

        return null;
    }

    /**
     * Prepare a prompt with data.
     */
    protected function preparePrompt(string $template, array $data, Brand $brand): string
    {
        $replacements = array_merge($data, [
            'brand_name' => $brand->name,
            'brand_domain' => $brand->domain_type,
            'brand_voice' => $brand->brand_voice,
            'date' => now()->toDateString(),
        ]);

        $prompt = $template;
        foreach ($replacements as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $prompt = str_replace("{{$key}}", $value ?? '', $prompt);
            } elseif (is_array($value)) {
                $prompt = str_replace("{{$key}}", json_encode($value, JSON_PRETTY_PRINT), $prompt);
            }
        }

        return $prompt;
    }

    /**
     * Log an incident.
     */
    protected function logIncident(Brand $brand, string $type, string $severity, string $description): void
    {
        GuardianIncident::create([
            'brand_id' => $brand->id,
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'status' => 'open',
        ]);
    }

    /**
     * Get domain capabilities.
     */
    public function getDomainCapabilities(Brand $brand): array
    {
        $rules = DomainRoutingRule::where('domain_type', $brand->domain_type)
            ->where('is_active', true)
            ->get();

        return [
            'domain_type' => $brand->domain_type,
            'intents' => $rules->pluck('intent_type')->toArray(),
            'available_models' => $rules->pluck('default_model')->unique()->toArray(),
        ];
    }

    /**
     * Check if a brand's domain is supported.
     */
    public function isDomainSupported(Brand $brand): bool
    {
        return DomainRoutingRule::where('domain_type', $brand->domain_type)
            ->where('is_active', true)
            ->exists();
    }
}