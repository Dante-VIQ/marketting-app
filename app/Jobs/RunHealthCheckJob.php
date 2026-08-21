<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Guardian\GuardianService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function handle(GuardianService $guardian): void
    {
        if (!$this->brand->is_active) {
            Log::info('Brand is not active, skipping health check', ['brand_id' => $this->brand->id]);
            return;
        }

        try {
            $guardian->checkHealth($this->brand);

            Log::info('Health check completed', [
                'brand_id' => $this->brand->id,
                'brand_name' => $this->brand->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}