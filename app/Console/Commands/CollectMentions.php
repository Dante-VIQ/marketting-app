<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Listening\ListeningCollector;
use Illuminate\Console\Command;

class CollectMentions extends Command
{
    protected $signature = 'listening:collect {brand? : Brand id (omit for all active brands)}';
    protected $description = 'Check Google News, Reddit and Hacker News for brand, competitor and topic mentions (free, no keys).';

    public function handle(ListeningCollector $collector): int
    {
        $brands = $this->argument('brand') ? Brand::whereKey($this->argument('brand'))->get() : Brand::where('is_active', true)->get();

        foreach ($brands as $brand) {
            try {
                $r = $collector->collectForBrand($brand);
                $this->line("{$brand->name}: {$r['queries']} searches, {$r['new_mentions']} new mentions");
            } catch (\Throwable $e) {
                $this->warn("{$brand->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
