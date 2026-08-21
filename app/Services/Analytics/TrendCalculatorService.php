<?php

namespace App\Services\Analytics;

use App\Models\Brand;
use App\Models\AnalyticsSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrendCalculatorService
{
    /**
     * Calculate trends for a brand's analytics data.
     */
    public function calculateTrendsForBrand(Brand $brand): void
    {
        $today = Carbon::today();
        $last7Days = $today->copy()->subDays(7);
        $last30Days = $today->copy()->subDays(30);

        // Get all snapshots for the last 30 days
        $snapshots = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('date', '>=', $last30Days->toDateString())
            ->get();

        // Group by metric and source
        $groups = $snapshots->groupBy(function ($item) {
            return $item->source . '|' . $item->metric . '|' . ($item->dimension ?? 'null');
        });

        foreach ($groups as $key => $items) {
            [$source, $metric, $dimension] = explode('|', $key);
            $dimension = $dimension === 'null' ? null : $dimension;

            // Calculate week-over-week change
            $lastWeek = $items->filter(function ($item) use ($last7Days) {
                return $item->date >= $last7Days->toDateString();
            });

            $previousWeek = $items->filter(function ($item) use ($last7Days, $today) {
                return $item->date < $last7Days->toDateString() 
                    && $item->date >= $today->copy()->subDays(14)->toDateString();
            });

            $currentAvg = $lastWeek->avg('value') ?: 0;
            $previousAvg = $previousWeek->avg('value') ?: 0;

            $changeWoW = $previousAvg > 0 
                ? round((($currentAvg - $previousAvg) / $previousAvg) * 100, 2) 
                : 0;

            // Calculate month-over-month change
            $lastMonth = $items->filter(function ($item) use ($last30Days) {
                return $item->date >= $last30Days->toDateString();
            });

            $previousMonth = $items->filter(function ($item) use ($last30Days, $today) {
                return $item->date < $last30Days->toDateString() 
                    && $item->date >= $today->copy()->subDays(60)->toDateString();
            });

            $currentMonthAvg = $lastMonth->avg('value') ?: 0;
            $previousMonthAvg = $previousMonth->avg('value') ?: 0;

            $changeMoM = $previousMonthAvg > 0 
                ? round((($currentMonthAvg - $previousMonthAvg) / $previousMonthAvg) * 100, 2) 
                : 0;

            // Update the most recent snapshot with trend data
            $latest = $items->sortByDesc('date')->first();
            if ($latest) {
                $latest->change_wo_w = $changeWoW;
                $latest->change_mo_m = $changeMoM;
                $latest->save();
            }
        }

        // Calculate revenue leaks
        $this->detectRevenueLeaks($brand);

        Log::info('Trends calculated', ['brand_id' => $brand->id]);
    }

    /**
     * Detect revenue leaks based on analytics data.
     */
    protected function detectRevenueLeaks(Brand $brand): void
    {
        // Get high-traffic pages with low conversion rates
        $pages = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('metric', 'visitors')
            ->whereNotNull('dimension')
            ->where('date', '>=', Carbon::today()->subDays(30)->toDateString())
            ->get();

        foreach ($pages as $page) {
            // Get leads for this page (if we have this data)
            $leads = AnalyticsSnapshot::where('brand_id', $brand->id)
                ->where('metric', 'leads')
                ->where('dimension', $page->dimension)
                ->where('date', $page->date)
                ->first();

            if ($leads && $page->value > 100) {
                $conversionRate = $leads->value / $page->value;
                $opportunity = '';

                if ($conversionRate < 0.02) {
                    $opportunity = "Page {$page->dimension} has high traffic ({$page->value}) but low conversion rate. Estimated revenue loss: $" . round($page->value * 0.01 * 50);
                    
                    // Store as revenue leak
                    \App\Models\RevenueLeak::updateOrCreate(
                        [
                            'brand_id' => $brand->id,
                            'page_url' => $page->dimension,
                            'detected_date' => Carbon::today()->toDateString(),
                        ],
                        [
                            'source' => 'analytics',
                            'estimated_loss' => round($page->value * 0.01 * 50, 2),
                            'traffic_loss' => $page->value * 0.1,
                            'conversion_loss' => 0.01,
                            'opportunity_description' => $opportunity,
                            'status' => 'open',
                        ]
                    );
                }
            }
        }
    }
}