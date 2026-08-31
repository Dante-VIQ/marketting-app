<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Python\PythonService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPythonMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function handle(PythonService $python): void
    {
        try {
            $result = $python->runScript('monitor', [
                'brand_id' => $this->brand->id,
            ]);

            if (isset($result['error'])) {
                Log::error('Python monitor error', ['error' => $result['error']]);
                return;
            }

            foreach ($result['opportunities'] ?? [] as $opportunity) {
                // Create or update action
                AiAction::updateOrCreate(
                    [
                        'brand_id' => $this->brand->id,
                        'title' => $opportunity['title'],
                        'metadata->opportunity_type' => $opportunity['type'],
                    ],
                    [
                        'category' => $this->mapCategory($opportunity['type']),
                                         'description' => $opportunity['description'],
                                         'target_url' => $opportunity['target_url'] ?? null,
                                         'estimated_impact' => $opportunity['impact'] ?? 500,
                                         'priority' => $this->mapPriority($opportunity['severity']),
                                         'status' => $opportunity['requires_approval'] ? 'pending' : 'autonomous_approved',
                                         'metadata' => array_merge(
                                             $opportunity['metadata'] ?? [],
                                             ['detected_at' => now()->toDateTimeString()]
                                         ),
                    ]
                );
            }

            Log::info('Python monitor completed', [
                'brand_id' => $this->brand->id,
                'opportunities' => count($result['opportunities'] ?? []),
            ]);

        } catch (\Exception $e) {
            Log::error('Python monitor job failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function mapCategory(string $type): string
    {
        $map = [
            'content_gap' => 'content',
            'seo_issue' => 'seo',
            'lead_opportunity' => 'strategy',
            'campaign_insight' => 'campaign',
            'anomaly' => 'analytics',
        ];
        return $map[$type] ?? 'strategy';
    }

    protected function mapPriority(string $severity): int
    {
        $map = [
            'critical' => 5,
            'high' => 4,
            'medium' => 3,
            'low' => 2,
        ];
        return $map[$severity] ?? 3;
    }
}
