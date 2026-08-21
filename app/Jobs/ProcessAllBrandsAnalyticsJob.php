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

class ProcessAllBrandsAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MonitorsSchedule;

    public function handle(): void
    {
        // Start monitoring
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
                    \App\Jobs\FetchAnalyticsForBrandJob::dispatch($brand);
                    $count++;
                } catch (\Exception $e) {
                    Log::error('Error processing brand analytics', [
                        'brand_id' => $brand->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->successMonitor("Processed {$count} brands successfully.");

        } catch (\Exception $e) {
            $this->failMonitor($e->getMessage());
            throw $e;
        }
    }
}