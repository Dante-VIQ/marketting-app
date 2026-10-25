<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Competitors\Discovery\CompetitorDiscovery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiscoverCompetitorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(public int $brandId)
    {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("competitor-discovery-{$this->brandId}"))->expireAfter(1200)->dontRelease()];
    }

    public function handle(CompetitorDiscovery $discovery): void
    {
        $brand = Brand::find($this->brandId);
        if (!$brand) {
            return;
        }
        try {
            $r = $discovery->run($brand);
            CompetitorDiscovery::remember($brand->id, ['state' => 'done', 'found' => $r['found'], 'notes' => $r['notes'], 'used_search' => $r['used_search']]);
        } catch (\DomainException $e) {
            CompetitorDiscovery::remember($brand->id, ['state' => 'failed', 'notes' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            Log::error('Competitor discovery failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
            CompetitorDiscovery::remember($brand->id, ['state' => 'failed', 'notes' => ['Discovery failed. Nothing was changed; try again later.']]);
        }
    }
}
