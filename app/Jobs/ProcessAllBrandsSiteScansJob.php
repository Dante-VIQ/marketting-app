<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Traits\MonitorsSchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Keeps PageSnapshot (and therefore SiteProfileService's site inventory)
 * fresh. Without this, PageSnapshot rows only ever get created reactively —
 * one URL at a time, whenever an SEO issue happens to get flagged on that
 * page — so the content generator's anti-hallucination "here are the pages
 * that actually exist" block stays sparse or empty indefinitely.
 */
class ProcessAllBrandsSiteScansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MonitorsSchedule;

    public function handle(): void
    {
        $this->startMonitor([
            'trigger' => 'scheduled',
            'time' => now()->toDateTimeString(),
        ]);

        try {
            $brands = Brand::where('is_active', true)->get();

            if ($brands->isEmpty()) {
                $this->successMonitor('No active brands found.');
                return;
            }

            $count = 0;
            foreach ($brands as $brand) {
                try {
                    if (empty($brand->base_url)) {
                        Log::warning('Skipping site scan: no website_url set', [
                            'brand_id' => $brand->id,
                        ]);
                        continue;
                    }

                    ScanAllPagesJob::dispatch($brand, $brand->base_url, 2);
                    $count++;
                } catch (\Exception $e) {
                    Log::error('Error dispatching site scan for brand', [
                        'brand_id' => $brand->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->successMonitor("Dispatched site scans for {$count} brands.");
        } catch (\Exception $e) {
            $this->failMonitor($e->getMessage());
        }
    }
}