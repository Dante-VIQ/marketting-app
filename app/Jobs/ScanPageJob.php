<?php

namespace App\Jobs;

use App\Models\AiAction;
use App\Models\Brand;
use App\Models\PageSnapshot;
use App\Services\Scanner\PageScannerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public array $backoff = [10, 30, 60];

    protected Brand $brand;
    protected string $url;
    protected ?AiAction $action;

    public function __construct(Brand $brand, string $url, ?AiAction $action = null)
    {
        $this->brand = $brand;
        $this->url = $url;
        $this->action = $action;
    }

    public function handle(PageScannerService $scanner): void
    {
        Log::info('ScanPageJob: Starting execution', [
            'brand_id' => $this->brand->id,
            'url' => $this->url,
            'action_id' => $this->action?->id,
            'attempt' => $this->attempts(),
        ]);

        try {
            $snapshot = $scanner->scanPage($this->url, $this->brand, $this->action);

            Log::info('ScanPageJob: Successfully completed', [
                'brand_id' => $this->brand->id,
                'url' => $this->url,
                'snapshot_id' => $snapshot->id,
            ]);
        } catch (Throwable $e) {
            Log::error('ScanPageJob: Exception encountered during scan', [
                'brand_id' => $this->brand->id,
                'url' => $this->url,
                'attempt' => $this->attempts(),
                       'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to allow Laravel queue worker backoff strategy to handle retries
        }
    }

    /**
     * Handle job failure when max attempts are exceeded or hard timeout occurs.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ScanPageJob: Job explicitly failed permanently', [
            'brand_id' => $this->brand->id,
            'url' => $this->url,
            'error' => $exception?->getMessage(),
        ]);

        // Clean up any stale processing snapshot in database
        PageSnapshot::where('brand_id', $this->brand->id)
        ->where('url', $this->url)
        ->where('status', 'processing')
        ->update([
            'status' => 'failed',
            'error_message' => $exception ? $exception->getMessage() : 'Job timed out or worker process terminated unexpectedly.',
        ]);
    }
}
