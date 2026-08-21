<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Analytics\AnalyticsCollectorService;
use App\Services\Analytics\TrendCalculatorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchAnalyticsForBrandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function handle(
        AnalyticsCollectorService $collector,
        TrendCalculatorService $trendCalculator
    ): void {
        if (!$this->brand->is_active) {
            Log::info('Brand is not active, skipping analytics', ['brand_id' => $this->brand->id]);
            return;
        }

        try {
            // Step 1: Collect analytics data
            $collector->collectForBrand($this->brand);

            // Step 2: Calculate trends
            $trendCalculator->calculateTrendsForBrand($this->brand);

            Log::info('Analytics fetched for brand', [
                'brand_id' => $this->brand->id,
                'brand_name' => $this->brand->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch analytics for brand', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}