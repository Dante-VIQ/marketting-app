<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Ahrefs\AhrefsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAllAhrefsDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AhrefsService $ahrefs): void
    {
        $brands = Brand::where('is_active', true)->get();

        Log::info('Processing Ahrefs data for all active brands', ['count' => $brands->count()]);

        foreach ($brands as $brand) {
            try {
                $ahrefs->collectForBrand($brand);
                Log::info('Ahrefs data collected for brand', ['brand_id' => $brand->id]);
            } catch (\Exception $e) {
                Log::error('Ahrefs data collection failed for brand', [
                    'brand_id' => $brand->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}