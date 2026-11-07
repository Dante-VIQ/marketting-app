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

    public int $timeout = 1800;
    public int $tries = 1;

    public function handle(AhrefsService $ahrefs): void
    {
        $brands = Brand::where('is_active', true)->get();

        Log::info('Processing Ahrefs data for all active brands', ['count' => $brands->count()]);

        foreach ($brands as $brand) {
            try {
                $result = $ahrefs->collectForBrand($brand);
                if (!empty($result['error'])) {
                    Log::warning('Ahrefs collection incomplete for brand', [
                        'brand_id' => $brand->id,
                        'error' => $result['error'],
                    ]);
                    continue;
                }
                Log::info('Ahrefs data collected for brand', [
                    'brand_id' => $brand->id,
                    'collected' => $result['collected'] ?? [],
                ]);
            } catch (\Exception $e) {
                Log::error('Ahrefs data collection failed for brand', [
                    'brand_id' => $brand->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}