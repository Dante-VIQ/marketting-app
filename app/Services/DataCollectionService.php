<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ScheduledTaskStatus;
use App\Jobs\ProcessAllBrandsAnalyticsJob;
use App\Jobs\ProcessAllSeoChecksJob;
use App\Jobs\ProcessAllBrandsBriefsJob;
use App\Jobs\ProcessAllBrandsLeadsJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DataCollectionService
{
    /**
     * Check freshness of each data type for a brand.
     * Uses ScheduledTaskStatus to determine if today's jobs have run.
     */
    public function isFresh(int $brandId): array
    {
        $today = Carbon::today();

        $taskStatus = fn(string $taskName) => ScheduledTaskStatus::where('task_name', $taskName)
            ->whereDate('last_run_at', $today)
            ->where('status', 'success')
            ->exists();

        return [
            'analytics' => $taskStatus('analytics-collection'),
            'seo' => $taskStatus('seo-checks'),
            'briefs' => $taskStatus('brief-generation'),
            'leads' => $taskStatus('lead-processing'),
        ];
    }

    /**
     * Ensure all data is collected for today by dispatching the same jobs
     * the scheduler uses. Returns a summary of what was queued.
     */
    public function ensureFreshData(int $brandId): array
    {
        $freshness = $this->isFresh($brandId);
        $queued = [];

        // If analytics is stale, dispatch the analytics job
        if (!$freshness['analytics']) {
            Log::info("📊 Queueing analytics collection");
            ProcessAllBrandsAnalyticsJob::dispatch();
            $queued[] = 'analytics';
        }

        // If SEO is stale, dispatch the SEO job
        if (!$freshness['seo']) {
            Log::info("🔍 Queueing SEO checks");
            ProcessAllSeoChecksJob::dispatch();
            $queued[] = 'seo';
        }

        // If briefs are stale, dispatch the brief job
        if (!$freshness['briefs']) {
            Log::info("📋 Queueing brief generation");
            ProcessAllBrandsBriefsJob::dispatch();
            $queued[] = 'briefs';
        }

        // If leads are stale, dispatch the lead job
        if (!$freshness['leads']) {
            Log::info("👤 Queueing lead processing");
            ProcessAllBrandsLeadsJob::dispatch();
            $queued[] = 'leads';
        }

        Log::info("✅ Data collection queued", [
            'brand_id' => $brandId,
            'queued' => $queued,
        ]);

 return [
    'queued'  => $queued,
    'message' => count($queued) > 0
        ? 'Data collection jobs queued.'
        : 'All data was already fresh.',
];
    }
}