<?php

namespace App\Jobs;

use App\Models\Brand;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAllBrandsBriefsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $brands = Brand::where('is_active', true)->get();

        Log::info('Dispatching per-brand brief jobs', ['count' => $brands->count()]);

        foreach ($brands as $brand) {
            GenerateBriefForBrandJob::dispatch($brand);
        }
    }
}