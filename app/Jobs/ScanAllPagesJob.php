<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Scanner\PageScannerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScanAllPagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;
    protected string $startUrl;
    protected int $depth;

    public function __construct(Brand $brand, string $startUrl, int $depth = 2)
    {
        $this->brand = $brand;
        $this->startUrl = $startUrl;
        $this->depth = $depth;
    }

    public function handle(PageScannerService $scanner): void
    {
        try {
            $results = $scanner->scanAllPages($this->brand, $this->startUrl, $this->depth);

            Log::info('All pages scan completed', [
                'brand_id' => $this->brand->id,
                'pages_scanned' => count($results),
                      'start_url' => $this->startUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('All pages scan failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
