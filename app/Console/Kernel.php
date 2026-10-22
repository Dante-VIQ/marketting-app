<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ============================================================
        // TEST TASK - Check if scheduler is working
        // ============================================================
        // $schedule->call(function () {
        //     Log::info('Scheduler test task executed at ' . now()->toDateTimeString());
        // })
        // ->name('test-scheduler')
        // ->everyMinute()
        // ->withoutOverlapping()
        // ->onOneServer()
        // ->runInBackground();

        // ============================================================
        // PHASE 1: Analytics Collection
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsAnalyticsJob())
            ->name('analytics-collection')
            ->dailyAt('05:30')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // PHASE 1.5: Site Scan (page inventory for content generation)
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsSiteScansJob())
            ->name('site-scan')
            ->weeklyOn(0, '04:00') // Sunday 04:00, ahead of daily brief/content jobs
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // PHASE 2: AI Brief Generation
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsBriefsJob())
            ->name('brief-generation')
            ->dailyAt('06:00')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // PHASE 4: Content Generation
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllApprovedActionsJob())
            ->name('content-generation')
            ->everyFiveMinutes()
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // PHASE 4: SEO Checks
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllSeoChecksJob())
            ->name('seo-checks')
            ->dailyAt('07:00')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // PHASE 5: Lead Processing
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsLeadsJob())
            ->name('lead-processing')
            ->everyFifteenMinutes()
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // PHASE 5: Campaign Performance
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsCampaignsJob())
            ->name('campaign-performance')
            ->dailyAt('08:00')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // PHASE 6: Health Checks (Guardian)
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllHealthChecksJob())
            ->name('health-checks')
            ->dailyAt('09:30')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // PHASE 6: Lead Follow-Ups
        // ============================================================
        $schedule->call(function () {
            try {
                $brands = \App\Models\Brand::where('is_active', true)->get();

                if ($brands->isEmpty()) {
                    Log::info('No active brands found for follow-up check.');
                    return;
                }

                $qualifier = app(\App\Services\Lead\LeadQualifierService::class);

                foreach ($brands as $brand) {
                    $qualifier->checkForFollowUps($brand);
                }

                Log::info('Follow-up check completed.', [
                    'brands_processed' => $brands->count(),
                ]);
            } catch (\Exception $e) {
                Log::error('Follow-up check failed.', [
                    'error' => $e->getMessage(),
                ]);
            }
        })
            ->name('lead-followups')
            ->dailyAt('10:00')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // ============================================================
        // Emergency: Hourly health check when incidents exist
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllHealthChecksJob())
            ->name('emergency-health-checks')
            ->hourly()
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer()
            ->when(function () {
                try {
                    $brand = \App\Models\Brand::where('is_active', true)->first();

                    if (!$brand) {
                        return false;
                    }

                    $incidents = app(\App\Services\Guardian\GuardianService::class)
                        ->getOpenIncidents($brand);

                    return count($incidents) > 0;
                } catch (\Exception $e) {
                    Log::error('Health check condition failed', [
                        'error' => $e->getMessage(),
                    ]);
                    return false;
                }
            });

        // 8:30 AM - Collect affiliate data
        $schedule->job(new \App\Jobs\ProcessAllAffiliateDataJob())
            ->name('affiliate-data-collection')
            ->dailyAt('08:30')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer()
            ->onSuccess(function () {
                Log::info('Affiliate data collection completed successfully.');
            })
            ->onFailure(function () {
                Log::error('Affiliate data collection failed.');
            });

        // 7:30 AM - Collect Ahrefs data
        $schedule->job(new \App\Jobs\ProcessAllAhrefsDataJob())
            ->name('ahrefs-data-collection')
            ->dailyAt('07:30')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer()
            ->onSuccess(function () {
                Log::info('Ahrefs data collection completed successfully.');
            })
            ->onFailure(function () {
                Log::error('Ahrefs data collection failed.');
            });

        // Mondays 06:00 - Scan competitors (free: homepage, sitemap, blog feed)
        $schedule->job(new \App\Jobs\ProcessAllCompetitorsJob())
            ->name('competitor-scan')
            ->weeklyOn(1, '06:00')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // 8:00 AM - Social listening (Google News, Reddit, Hacker News; free)
        $schedule->job(new \App\Jobs\ProcessAllListeningJob())
            ->name('social-listening')
            ->dailyAt('08:00')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer();

        // Meta: import recent posts + refresh results daily; publish human-approved posts when due.
        $schedule->command('meta:sync-results')
            ->dailyAt('09:15')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('meta:sync-data --only=ads')
            ->dailyAt('06:30')
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('meta:sync-data --only=comments,leads')
            ->everyThirtyMinutes()
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('meta:publish-due')
            ->everyFiveMinutes()
            ->environments(['production', 'staging'])
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
    }

    /**
     * PUBLIC WRAPPER: Expose the schedule for bootstrap/app.php
     * This allows the schedule to be loaded from the Kernel.
     */
    public function defineSchedule(Schedule $schedule): void
    {
        $this->schedule($schedule);
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
