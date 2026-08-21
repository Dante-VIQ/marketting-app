<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Affiliate\AffiliateDataCollectorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CollectAffiliateDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function handle(AffiliateDataCollectorService $collector): void
    {
        if (!$this->brand->is_active) {
            Log::info('Brand is not active, skipping affiliate data collection', [
                'brand_id' => $this->brand->id,
            ]);
            return;
        }

        try {
            $collector->collectForBrand($this->brand);

            Log::info('Affiliate data collected for brand', [
                'brand_id' => $this->brand->id,
                'brand_name' => $this->brand->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Affiliate data collection failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}