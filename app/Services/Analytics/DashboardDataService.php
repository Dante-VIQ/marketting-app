<?php

namespace App\Services\Analytics;

use App\Models\Brand;
use App\Models\AnalyticsSnapshot;
use App\Services\Analytics\GA4Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardDataService
{
    protected GA4Service $ga4Service;

    public function __construct(GA4Service $ga4Service)
    {
        $this->ga4Service = $ga4Service;
    }

 public function getDashboardData(Brand $brand): array
{
    $today = Carbon::today();
    $last7Days = $today->copy()->subDays(7);

    $visitors = $this->getMetricSum($brand, 'ga4', 'visitors', $last7Days);
    $leads = $this->getMetricSum($brand, 'database', 'leads', $last7Days);
    $socialReach = $this->getMetricSum($brand, 'facebook', 'reach', $last7Days) + 
                   $this->getMetricSum($brand, 'linkedin', 'followers', $last7Days);
    $seoImpressions = $this->getMetricSum($brand, 'search_console', 'impressions', $last7Days);

    return [
        'visitors' => $visitors,
        'leads' => $leads,
        'social_reach' => $socialReach,
        'seo_impressions' => $seoImpressions,
        'last_updated' => Carbon::now()->toDateTimeString(),
    ];
}

    /**
     * Get sum of a metric for a brand.
     */
    protected function getMetricSum(Brand $brand, string $source, string $metric, Carbon $since): float
    {
        return AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', $source)
            ->where('metric', $metric)
            ->where('date', '>=', $since->toDateString())
            ->sum('value');
    }

    /**
     * Get top pages for a brand.
     */
    public function getTopPages(Brand $brand, int $limit = 5): array
    {
        $last30Days = Carbon::today()->subDays(30);

        $results = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('metric', 'visitors')
            ->whereNotNull('dimension')
            ->where('date', '>=', $last30Days->toDateString())
            ->select('dimension')
            ->selectRaw('SUM(value) as total_visitors')
            ->groupBy('dimension')
            ->orderBy('total_visitors', 'desc')
            ->limit($limit)
            ->get();

        return $results->map(function ($item) {
            return [
                'dimension' => $item->dimension ?? '/',
                'path' => $item->dimension ?? '/',
                'total_visitors' => $item->total_visitors ?? 0,
                'visitors' => $item->total_visitors ?? 0,
            ];
        })->toArray();
    }
}