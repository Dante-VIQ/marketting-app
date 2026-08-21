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
        // ->withoutOverlapping();

        // ============================================================
        // PHASE 1: Analytics Collection
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsAnalyticsJob())
            ->name('analytics-collection')
            ->dailyAt('05:30')
            ->withoutOverlapping();

        // ============================================================
        // PHASE 2: AI Brief Generation
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsBriefsJob())
            ->name('brief-generation')
            ->dailyAt('06:00')
            ->withoutOverlapping();

        // ============================================================
        // PHASE 4: Content Generation
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllApprovedActionsJob())
            ->name('content-generation')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // ============================================================
        // PHASE 4: SEO Checks
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllSeoChecksJob())
            ->name('seo-checks')
            ->dailyAt('07:00')
            ->withoutOverlapping();

        // ============================================================
        // PHASE 5: Lead Processing
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsLeadsJob())
            ->name('lead-processing')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        // ============================================================
        // PHASE 5: Campaign Performance
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllBrandsCampaignsJob())
            ->name('campaign-performance')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        // ============================================================
        // PHASE 6: Health Checks (Guardian)
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllHealthChecksJob())
            ->name('health-checks')
            ->dailyAt('09:30')
            ->withoutOverlapping();

        // ============================================================
        // PHASE 6: Lead Follow-Ups (Callback - NEEDS name)
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
            ->withoutOverlapping();

        // ============================================================
        // Emergency: Hourly health check when incidents exist
        // ============================================================
        $schedule->job(new \App\Jobs\ProcessAllHealthChecksJob())
            ->name('emergency-health-checks')
            ->hourly()
            ->withoutOverlapping()
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

        // In schedule() method
        // 8:30 AM - Collect affiliate data
        $schedule->job(new \App\Jobs\ProcessAllAffiliateDataJob())
            ->dailyAt('08:30')
            ->withoutOverlapping()
            ->onSuccess(function () {
                Log::info('Affiliate data collection completed successfully.');
            })
            ->onFailure(function () {
                Log::error('Affiliate data collection failed.');
            });

            // 7:30 AM - Collect Ahrefs data
$schedule->job(new \App\Jobs\ProcessAllAhrefsDataJob())
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Ahrefs data collection completed successfully.');
    })
    ->onFailure(function () {
        Log::error('Ahrefs data collection failed.');
    });
    
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
