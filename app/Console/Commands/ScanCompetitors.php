<?php

namespace App\Console\Commands;

use App\Models\Competitor;
use App\Services\Competitors\CompetitorScanner;
use Illuminate\Console\Command;

class ScanCompetitors extends Command
{
    protected $signature = 'competitors:scan {brand? : Brand id (omit for all brands)}';
    protected $description = 'Scan active competitors (homepage, sitemap, blog feed). Free: no API keys.';

    public function handle(CompetitorScanner $scanner): int
    {
        $query = Competitor::where('is_active', true);
        if ($this->argument('brand')) {
            $query->where('brand_id', $this->argument('brand'));
        }

        $competitors = $query->get();
        if ($competitors->isEmpty()) {
            $this->info('No active competitors to scan.');
            return self::SUCCESS;
        }

        foreach ($competitors as $c) {
            try {
                $s = $scanner->scan($c);
                $this->line(sprintf('%-30s %s%s', $c->domain, $s->status, $s->error ? " - {$s->error}" : ''));
            } catch (\Throwable $e) {
                $this->warn("{$c->domain}: {$e->getMessage()}");
            }
            usleep(500_000);   // be polite to other people's servers
        }

        return self::SUCCESS;
    }
}
