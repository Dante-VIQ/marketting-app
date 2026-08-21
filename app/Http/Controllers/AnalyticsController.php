<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Models\RevenueLeak;
use App\Services\Analytics\AnalyticsCollectorService;
use App\Services\Analytics\DashboardDataService;
use App\Services\Analytics\GA4Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    protected GA4Service $ga4Service;
    protected DashboardDataService $dashboardDataService;
    protected AnalyticsCollectorService $analyticsCollector;

    public function __construct(
        GA4Service $ga4Service,
        DashboardDataService $dashboardDataService,
        AnalyticsCollectorService $analyticsCollector
    ) {
        $this->ga4Service = $ga4Service;
        $this->dashboardDataService = $dashboardDataService;
        $this->analyticsCollector = $analyticsCollector;
    }


   /**
     * Display the analytics dashboard with filters.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        // Get filter parameters
        $period = $request->get('period', '30d');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $metric = $request->get('metric', 'visitors');
        $source = $request->get('source', 'ga4');

        // Determine date range based on period
        [$startDate, $endDate] = $this->getDateRange($period, $startDate, $endDate);

        // Get real-time data
        $realtimeData = [];
        $ga4Data = [];
        
        try {
            $ga4Data = $this->ga4Service->getDashboardData($brand);
            $realtimeData = $ga4Data['realtime'] ?? ['total_active_users' => 0];
        } catch (\Exception $e) {
            \Log::warning('GA4 realtime data unavailable', ['error' => $e->getMessage()]);
        }

        // Get historical data with filters
        $historicalData = $this->getHistoricalData($brand, $startDate, $endDate, $metric, $source);
        
        // Get trend data
        $trendData = $this->getTrendData($brand, $startDate, $endDate, $metric, $source);
        
        // Get top pages with filters
        $topPages = $this->getTopPages($brand, $startDate, $endDate);
        
        // Get channel breakdown
        $channels = $this->getChannelBreakdown($brand, $startDate, $endDate);
        
        // Get revenue leaks
        $revenueLeaks = RevenueLeak::where('brand_id', $brand->id)
            ->where('status', 'open')
            ->orderBy('estimated_loss', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        // Get GA4 configuration status
        $ga4Configured = $this->ga4Service->isConfigured($brand);
        $config = $brand->config;

        // Calculate summary statistics
        $summary = $this->calculateSummary($historicalData);

        // Get last collection time
        $lastCollection = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return view('analytics.index', compact(
            'brand',
            'realtimeData',
            'historicalData',
            'trendData',
            'topPages',
            'channels',
            'revenueLeaks',
            'ga4Configured',
            'config',
            'summary',
            'period',
            'startDate',
            'endDate',
            'metric',
            'source',
            'lastCollection'
        ));
    }

/**
 * Fetch analytics data manually.
 */
public function fetch(Request $request)
{
    $user = Auth::user();
    $brand = $user->activeBrand;

    if (!$brand) {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['error' => 'Brand not found'], 404);
        }
        return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
    }

    try {
        // Collect analytics data
        $this->analyticsCollector->collectForBrand($brand);

        $message = 'Analytics data fetched successfully.';
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'brand' => $brand->name,
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        return redirect()->route('analytics.index')->with('message', $message);
    } catch (\Exception $e) {
        $error = 'Failed to fetch analytics: ' . $e->getMessage();
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $error,
            ], 500);
        }

        return redirect()->route('analytics.index')->with('error', $error);
    }
}
    /**
     * Get date range based on period filter.
     */
    protected function getDateRange(string $period, ?string $startDate, ?string $endDate): array
    {
        if ($startDate && $endDate) {
            return [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ];
        }

        $end = Carbon::today();
        $start = match ($period) {
            'today' => Carbon::today(),
            'yesterday' => Carbon::yesterday(),
            '7d' => Carbon::today()->subDays(7),
            '14d' => Carbon::today()->subDays(14),
            '30d' => Carbon::today()->subDays(30),
            '60d' => Carbon::today()->subDays(60),
            '90d' => Carbon::today()->subDays(90),
            'this_month' => Carbon::today()->startOfMonth(),
            'last_month' => Carbon::today()->subMonth()->startOfMonth(),
            'this_year' => Carbon::today()->startOfYear(),
            default => Carbon::today()->subDays(30)
        };

        return [$start, $end];
    }

    /**
     * Get historical data with filters.
     */
    protected function getHistoricalData(Brand $brand, Carbon $startDate, Carbon $endDate, string $metric, string $source): array
    {
        $query = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', $source)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);

        // If specific metric, filter by it
        if ($metric !== 'all') {
            $query->where('metric', $metric);
        }

        // Get daily aggregated data
        $data = $query->select(
            'date',
            'metric',
            \DB::raw('SUM(value) as total_value')
        )
        ->groupBy('date', 'metric')
        ->orderBy('date', 'asc')
        ->get();

        // Group by date
        $grouped = [];
        foreach ($data as $row) {
            // Convert date to string if it's a Carbon object
            $dateKey = $row->date instanceof \DateTime ? $row->date->toDateString() : (string) $row->date;
            
            if (!isset($grouped[$dateKey])) {
                $grouped[$dateKey] = [];
            }
            $grouped[$dateKey][$row->metric] = $row->total_value;
        }

        return $grouped;
    }

    /**
     * Get trend data for the selected period.
     */
    protected function getTrendData(Brand $brand, Carbon $startDate, Carbon $endDate, string $metric, string $source): array
    {
        $query = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', $source)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($metric !== 'all') {
            $query->where('metric', $metric);
        }

        // Weekly aggregation for trends
        $data = $query->select(
            \DB::raw('YEARWEEK(date, 1) as week'),
            'metric',
            \DB::raw('AVG(value) as avg_value')
        )
        ->groupBy('week', 'metric')
        ->orderBy('week', 'asc')
        ->get();

        $trends = [];
        foreach ($data as $row) {
            $weekKey = (string) $row->week;
            
            if (!isset($trends[$row->metric])) {
                $trends[$row->metric] = [];
            }
            $trends[$row->metric][] = [
                'week' => $weekKey,
                'avg_value' => (float) $row->avg_value,
            ];
        }

        return $trends;
    }

    /**
     * Get top pages for the selected period.
     */
    protected function getTopPages(Brand $brand, Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        return AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('metric', 'visitors')
            ->whereNotNull('dimension')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select('dimension')
            ->selectRaw('SUM(value) as total_visitors')
            ->groupBy('dimension')
            ->orderBy('total_visitors', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get channel breakdown for the selected period.
     */
    protected function getChannelBreakdown(Brand $brand, Carbon $startDate, Carbon $endDate): array
    {
        return AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('metric', 'visitors')
            ->where('dimension', 'like', 'source_%')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select('dimension')
            ->selectRaw('SUM(value) as total_visitors')
            ->groupBy('dimension')
            ->orderBy('total_visitors', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Calculate summary statistics.
     */
    protected function calculateSummary(array $data): array
    {
        if (empty($data)) {
            return [
                'total' => 0,
                'avg' => 0,
                'max' => 0,
                'min' => 0,
                'count' => 0,
            ];
        }

        // Flatten all metrics
        $values = [];
        foreach ($data as $day) {
            foreach ($day as $metric => $value) {
                if (is_numeric($value)) {
                    $values[] = (float) $value;
                }
            }
        }

        if (empty($values)) {
            return [
                'total' => 0,
                'avg' => 0,
                'max' => 0,
                'min' => 0,
                'count' => 0,
            ];
        }

        return [
            'total' => array_sum($values),
            'avg' => round(array_sum($values) / count($values), 2),
            'max' => max($values),
            'min' => min($values),
            'count' => count($values),
        ];
    }
}