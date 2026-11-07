<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Meta\ResultsSync;
use Illuminate\Console\Command;

class MetaSyncResults extends Command
{
    protected $signature = 'meta:sync-results {brand? : Brand id (omit for all active brands)}';
    protected $description = 'Import recent Facebook/Instagram posts and refresh their results into the Content Plan';

    public function handle(ResultsSync $sync): int
    {
        $brands = $this->argument('brand') ? Brand::whereKey($this->argument('brand'))->get() : Brand::where('is_active', true)->get();
        foreach ($brands as $brand) {
            $r = $sync->run($brand->id);
            $this->line("{$brand->name}: imported {$r['imported']}, refreshed {$r['updated']}");
            foreach ($r['errors'] as $e) {
                $this->warn("  {$e}");
            }
        }

        return self::SUCCESS;
    }
}
