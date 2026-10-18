<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Listening\ListeningCollector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAllListeningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function handle(ListeningCollector $collector): void
    {
        foreach (Brand::where('is_active', true)->get() as $brand) {
            try {
                $r = $collector->collectForBrand($brand);
                Log::info('Listening collected', ['brand_id' => $brand->id] + $r);
            } catch (\Throwable $e) {
                Log::error('Listening collection failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
