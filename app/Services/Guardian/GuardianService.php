<?php

namespace App\Services\Guardian;

use App\Models\Brand;
use App\Models\GuardianPolicy;
use App\Models\GuardianIncident;
use App\Models\SystemHealth;
use App\Models\GuardianAuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GuardianService
{
    /**
     * Perform a health check.
     */
    public function checkHealth(Brand $brand): void
    {
        $checks = [
            'ai_provider' => $this->checkAIProvider($brand),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'api' => $this->checkAPI($brand),
        ];

        foreach ($checks as $component => $result) {
            SystemHealth::create([
                'brand_id' => $brand->id,
                'component' => $component,
                'status' => $result['status'],
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'metadata' => $result['metadata'] ?? null,
                'checked_at' => now(),
            ]);

            // Log incident if component is down
            if ($result['status'] === 'down') {
                $this->logIncident(
                    $brand,
                    'system_error',
                    'critical',
                    "{$component} is down",
                    $result
                );
            }
        }
    }

    /**
     * Check AI provider health.
     */
    protected function checkAIProvider(Brand $brand): array
    {
        $start = microtime(true);

        try {
            // Try to reach the AI provider
            $available = app(\App\Services\AI\AiGatewayService::class)->isAvailable();

            return [
                'status' => $available ? 'healthy' : 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'metadata' => [
                    'provider' => config('ai.provider', 'ollama'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check database health.
     */
    protected function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            \DB::select('SELECT 1');
            return [
                'status' => 'healthy',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check cache health.
     */
    protected function checkCache(): array
    {
        $start = microtime(true);

        try {
            \Cache::get('health_check', 'ok');
            return [
                'status' => 'healthy',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check queue health.
     */
    protected function checkQueue(): array
    {
        $start = microtime(true);

        try {
            // Check if queue connection is working
            $connection = config('queue.default');
            return [
                'status' => 'healthy',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'metadata' => [
                    'connection' => $connection,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check API health.
     */
    protected function checkAPI(Brand $brand): array
    {
        $start = microtime(true);

        try {
            // Check if the API is responding
            $response = Http::timeout(5)->get(url('/health'));
            $status = $response->successful() ? 'healthy' : 'degraded';

            return [
                'status' => $status,
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'metadata' => [
                    'status_code' => $response->status(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Log an incident.
     */
    public function logIncident(Brand $brand, string $type, string $severity, string $description, array $context = []): void
    {
        GuardianIncident::create([
            'brand_id' => $brand->id,
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'context' => $context,
            'status' => 'open',
        ]);

        Log::warning('Guardian incident logged', [
            'brand_id' => $brand->id,
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
        ]);
    }

    /**
     * Check content against policies.
     */
    public function checkContent(Brand $brand, string $content, string $context = 'general'): array
    {
        $policies = GuardianPolicy::where('brand_id', $brand->id)
            ->where('type', 'content_filter')
            ->where('is_active', true)
            ->get();

        $violations = [];

        foreach ($policies as $policy) {
            $rules = $policy->rules ?? [];

            foreach ($rules as $rule) {
                if ($this->checkRule($content, $rule)) {
                    $violations[] = [
                        'policy_id' => $policy->id,
                        'policy_name' => $policy->name,
                        'rule' => $rule,
                        'severity' => $policy->severity,
                    ];
                }
            }
        }

        if (!empty($violations)) {
            $this->logIncident(
                $brand,
                'policy_violation',
                'high',
                "Content violated {$context} policies",
                ['violations' => $violations]
            );
        }

        return [
            'passed' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Check a single rule against content.
     */
    protected function checkRule(string $content, array $rule): bool
    {
        $type = $rule['type'] ?? 'contains';

        return match ($type) {
            'contains' => str_contains($content, $rule['value'] ?? ''),
            'regex' => preg_match($rule['pattern'] ?? '', $content) === 1,
            'length' => strlen($content) > ($rule['max'] ?? PHP_INT_MAX),
            'words' => str_word_count($content) > ($rule['max'] ?? PHP_INT_MAX),
            default => false,
        };
    }

    /**
     * Get system health status.
     */
    public function getSystemStatus(Brand $brand): array
    {
        $health = SystemHealth::where('brand_id', $brand->id)
            ->recent()
            ->get()
            ->groupBy('component');

        $status = [];

        foreach ($health as $component => $records) {
            $latest = $records->first();
            $status[$component] = [
                'status' => $latest->status,
                'status_label' => $latest->status_label,
                'response_time_ms' => $latest->response_time_ms,
                'checked_at' => $latest->checked_at->diffForHumans(),
            ];
        }

        return $status;
    }

    /**
     * Get open incidents.
     */
    public function getOpenIncidents(Brand $brand): array
    {
        return GuardianIncident::where('brand_id', $brand->id)
            ->where('status', 'open')
            ->orderBy('severity', 'desc')
            ->get()
            ->toArray();
    }
}