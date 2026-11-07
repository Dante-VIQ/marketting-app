<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\ContentPlan\SocialSiteResults;
use Illuminate\Console\Command;

class SocialSyncSiteResults extends Command
{
    protected $signature = 'social:sync-site-results {brand? : Brand id (omit for all active brands)}';
    protected $description = 'Record how many website visits and key events each tagged social post produced (from GA4)';

    public function handle(SocialSiteResults $svc): int
    {
        $brands = $this->argument('brand') ? Brand::whereKey($this->argument('brand'))->get() : Brand::where('is_active', true)->get();
        foreach ($brands as $brand) {
            $r = $svc->run($brand);
            $this->line("{$brand->name}: site results recorded for {$r['updated']} posts");
            foreach ($r['errors'] as $e) {
                $this->warn("  {$e}");
            }
        }

        return self::SUCCESS;
    }
}
