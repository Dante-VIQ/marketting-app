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
        $brandIdsWithRecentBrief = \App\Models\AiBrief::where('created_at', '>=', now()->subHours(24))
            ->distinct()
            ->pluck('brand_id')
            ->all();

        $brands = Brand::where('is_active', true)
            ->when(!empty($brandIdsWithRecentBrief), function ($query) use ($brandIdsWithRecentBrief) {
                $query->whereNotIn('id', $brandIdsWithRecentBrief);
            })
            ->get();

        Log::info('Dispatching per-brand brief jobs', ['count' => $brands->count()]);

        foreach ($brands as $brand) {
            GenerateBriefForBrandJob::dispatch($brand);
        }
    }
}
