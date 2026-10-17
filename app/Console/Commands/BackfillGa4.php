<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Analytics\AnalyticsCollectorService;
use Illuminate\Console\Command;

class BackfillGa4 extends Command
{
    protected $signature = 'analytics:backfill-ga4 {brand? : Brand id (omit for all brands)} {--days=90 : How many days back, 1-180}';
    protected $description = 'Re-pull real per-day GA4 data for a longer range, replacing old averaged rows from earlier collector versions.';

    public function handle(AnalyticsCollectorService $collector): int
    {
        $days = (int) $this->option('days');
        if ($days < 1 || $days > 180) {
            $this->error('--days must be between 1 and 180.');
            return self::FAILURE;
        }

        $brands = $this->argument('brand')
            ? Brand::whereKey($this->argument('brand'))->get()
            : Brand::all();

        if ($brands->isEmpty()) {
            $this->error('No brands found.');
            return self::FAILURE;
        }

        $failed = 0;
        foreach ($brands as $brand) {
            try {
                $collector->collectForBrand($brand, $days);
                $this->info("Brand {$brand->id} ({$brand->name}): backfilled {$days} days.");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Brand {$brand->id} ({$brand->name}): skipped - {$e->getMessage()}");
            }
        }

        return $failed === $brands->count() ? self::FAILURE : self::SUCCESS;
    }
}
