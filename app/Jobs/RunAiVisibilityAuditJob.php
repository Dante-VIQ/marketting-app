<?php

namespace App\Jobs;

use App\Models\AiVisibilityAudit;
use App\Models\Brand;
use App\Services\Visibility\AiVisibilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAiVisibilityAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 420; // 7 minutes — generous for 12+ AI calls
    public int $tries   = 1;   // don't retry; partial audits cost real API quota

    public function __construct(public Brand $brand) {}

    public function handle(AiVisibilityService $service): void
    {
        Log::info('RunAiVisibilityAuditJob started', ['brand' => $this->brand->id]);
        $service->run($this->brand);
        Log::info('RunAiVisibilityAuditJob done', ['brand' => $this->brand->id]);
    }

    /**
     * Called by the queue worker when the job exceeds $timeout.
     *
     * Without this, the audit row stays in status='running' forever.
     * This hook marks it failed so the UI shows an error and allows retry.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RunAiVisibilityAuditJob timed out or failed', [
            'brand_id' => $this->brand->id,
            'error'    => $exception->getMessage(),
        ]);

        // Find the most recent running audit for this brand and mark it failed
        AiVisibilityAudit::where('brand_id', $this->brand->id)
            ->where('status', 'running')
            ->orderByDesc('created_at')
            ->limit(1)
            ->update([
                'status' => 'failed',
                'error'  => 'Audit job failed: ' . $exception->getMessage(),
            ]);
    }
}
