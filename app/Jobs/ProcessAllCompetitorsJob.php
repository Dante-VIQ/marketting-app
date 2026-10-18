<?php

namespace App\Jobs;

use App\Models\Competitor;
use App\Services\Competitors\CompetitorScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAllCompetitorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function handle(CompetitorScanner $scanner): void
    {
        foreach (Competitor::where('is_active', true)->get() as $c) {
            try {
                $s = $scanner->scan($c);
                if ($s->status !== 'ok') {
                    Log::warning('Competitor scan incomplete', ['competitor_id' => $c->id, 'domain' => $c->domain, 'error' => $s->error]);
                }
            } catch (\Throwable $e) {
                Log::error('Competitor scan failed', ['competitor_id' => $c->id, 'error' => $e->getMessage()]);
            }
            usleep(500_000);
        }
    }
}
