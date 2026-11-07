<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Ahrefs\AhrefsApi;
use App\Services\Ahrefs\AhrefsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DiagnoseAhrefs extends Command
{
    protected $signature = 'ahrefs:diagnose {brand? : Brand id (default: first active brand)}';
    protected $description = 'Call each Ahrefs endpoint once and print exactly what Ahrefs answers';

    public function handle(AhrefsApi $api, AhrefsService $service): int
    {
        $brand = $this->argument('brand') ? Brand::find($this->argument('brand')) : Brand::where('is_active', true)->first();
        if (!$brand) {
            $this->error('No brand found.');
            return self::FAILURE;
        }

        $domain = $service->getTargetDomain($brand);
        $this->line("Token:    {$api->tokenHint()}");
        $this->line("Endpoint: {$api->endpoint()}");
        $this->line("Brand:    #{$brand->id} {$brand->name}");
        $this->line('Domain:   ' . ($domain ?: 'NONE (set the website URL in Brand Settings)'));
        if (!$domain || $api->tokenHint() === 'NOT SET') {
            $this->error('Fix the above first; nothing was requested.');
            return self::FAILURE;
        }

        $date = Carbon::today()->toDateString();
        $country = $brand->config['ahrefs_country'] ?? null;
        $checks = [
            ['site-explorer/domain-rating', ['target' => $domain, 'date' => $date]],
            ['site-explorer/metrics', array_filter(['target' => $domain, 'date' => $date, 'country' => $country])],
            ['site-explorer/backlinks-stats', ['target' => $domain, 'date' => $date]],
            ['site-explorer/organic-keywords', ['target' => $domain, 'date' => $date, 'select' => 'keyword,best_position', 'limit' => 1]],
            ['site-explorer/all-backlinks', ['target' => $domain, 'select' => 'url_from', 'limit' => 1]],
        ];

        $failed = 0;
        foreach ($checks as [$path, $query]) {
            $r = $api->probe($path, $query);
            $r['ok'] ? $this->info("OK   {$path} (HTTP {$r['status']})") : $this->error("FAIL {$path} (HTTP " . ($r['status'] ?? 'none') . "): {$r['body']}");
            $failed += $r['ok'] ? 0 : 1;
        }

        $this->line($failed ? "\n{$failed} of " . count($checks) . ' endpoints failed. The message after each FAIL is what Ahrefs said.' : "\nAll endpoints answered. If the page is still empty, run the collection: it stores what these return.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
