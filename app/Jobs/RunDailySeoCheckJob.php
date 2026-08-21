<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\AI\SeoAssistantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDailySeoCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function handle(SeoAssistantService $seoAssistant): void
    {
        if (!$this->brand->is_active) {
            Log::info('Brand is not active, skipping SEO check', ['brand_id' => $this->brand->id]);
            return;
        }

        try {
            $seoAssistant->performDailyChecks($this->brand);

            Log::info('SEO check completed', [
                'brand_id' => $this->brand->id,
                'brand_name' => $this->brand->name,
            ]);
        } catch (\Exception $e) {
            Log::error('SEO check failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}