<?php

namespace App\Jobs;

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

    public int $timeout     = 300; // 5 minutes — multiple AI calls
    public int $tries       = 1;   // don't retry; a partial audit is better than a double-charge

    public function __construct(public Brand $brand) {}

    public function handle(AiVisibilityService $service): void
    {
        Log::info('RunAiVisibilityAuditJob started', ['brand' => $this->brand->id]);
        $service->run($this->brand);
        Log::info('RunAiVisibilityAuditJob done', ['brand' => $this->brand->id]);
    }
}
