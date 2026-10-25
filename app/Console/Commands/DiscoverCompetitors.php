<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverCompetitorsJob;
use App\Models\Brand;
use Illuminate\Console\Command;

class DiscoverCompetitors extends Command
{
    protected $signature = 'competitors:discover {brand? : Brand id (omit for all active brands)}';
    protected $description = 'Suggest competitors from what the system knows about each brand (a human accepts or dismisses them)';

    public function handle(): int
    {
        $brands = $this->argument('brand') ? Brand::whereKey($this->argument('brand'))->get() : Brand::where('is_active', true)->get();
        foreach ($brands as $brand) {
            DiscoverCompetitorsJob::dispatchSync($brand->id);
            $st = \Illuminate\Support\Facades\Cache::get(\App\Services\Competitors\Discovery\CompetitorDiscovery::statusKey($brand->id), []);
            $this->line("{$brand->name}: " . ($st['state'] ?? 'no result') . (isset($st['found']) ? ", {$st['found']} suggestions" : ''));
            foreach ($st['notes'] ?? [] as $n) {
                $this->warn("  {$n}");
            }
        }

        return self::SUCCESS;
    }
}
