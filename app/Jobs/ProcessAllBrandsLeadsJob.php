<?php

namespace App\Jobs;

use App\Models\Brand;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAllBrandsLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $brands = Brand::where('is_active', true)->get();

        Log::info('Processing leads for all active brands', ['count' => $brands->count()]);

        foreach ($brands as $brand) {
            ProcessNewLeadsJob::dispatch($brand);
        }
    }
}