<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\AI\BriefGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateBriefForBrandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function handle(BriefGeneratorService $briefGenerator): void
    {
        if (!$this->brand->is_active) {
            Log::info('Brand is not active, skipping brief generation', ['brand_id' => $this->brand->id]);
            return;
        }

        try {
            $brief = $briefGenerator->generateForBrand($this->brand);

            if ($brief) {
                Log::info('Brief generated successfully', [
                    'brand_id' => $this->brand->id,
                    'brief_id' => $brief->id,
                ]);
            } else {
                Log::warning('Brief generation failed or returned null', [
                    'brand_id' => $this->brand->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate brief', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}