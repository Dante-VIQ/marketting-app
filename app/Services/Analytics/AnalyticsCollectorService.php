<?php

namespace App\Services\Analytics;

use App\Models\Brand;
use App\Models\AnalyticsSnapshot;
use App\Services\Analytics\GA4Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalyticsCollectorService
{
    protected GA4Service $ga4Service;

    public function __construct(GA4Service $ga4Service)
    {
        $this->ga4Service = $ga4Service;
    }

    /**
     * Collect analytics for a specific brand.
     * 
     * @throws \Exception
     */
    public function collectForBrand(Brand $brand, ?int $backfillDays = null): void
    {
        $date = Carbon::yesterday();

        Log::info('Starting analytics collection', [
            'brand_id' => $brand->id,
            'brand_name' => $brand->name,
            'date' => $date->toDateString(),
        ]);

        // Check if GA4 is configured
        if (!$this->ga4Service->isConfigured($brand)) {
            throw new \Exception(sprintf(
                'GA4 is not configured for brand: %s. Please add GA4 credentials in Brand Settings.',
                $brand->name
            ));
        }

        try {
            // Collect from GA4 (REAL DATA ONLY)
            $this->collectFromGA4($brand, $date, $backfillDays);

            Log::info('Analytics collection completed', [
                'brand_id' => $brand->id,
                'date' => $date->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics collection failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /** How far back the first collection (or a long gap) reaches. */
    public const BACKFILL_DAYS = 30;
    /** Re-pull this many days before the last stored day: GA4 revises recent days. */
    public const REFRESH_DAYS = 3;

    /**
     * Date range to collect: from (last stored day - REFRESH_DAYS) up to yesterday,
     * at most BACKFILL_DAYS back. Today is excluded (GA4 data for today is partial).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function collectionWindow(?Carbon $lastStored, ?Carbon $today = null, ?int $forceDays = null): array
    {
        $today     = ($today ?? Carbon::today())->copy()->startOfDay();
        $end       = $today->copy()->subDay();
        $earliest  = $today->copy()->subDays($forceDays ?? self::BACKFILL_DAYS);
        // An explicit backfill ignores what is stored: re-pull the whole range.
        $start     = ($lastStored && $forceDays === null) ? $lastStored->copy()->startOfDay()->subDays(self::REFRESH_DAYS) : $earliest;

        if ($start->lt($earliest)) {
            $start = $earliest;
        }
        if ($start->gt($end)) {
            $start = $end->copy();
        }

        return [$start, $end];
    }

    /**
     * Collect real per-day data from Google Analytics 4 (totals, pages, channels).
     *
     * @throws \Exception
     */
    protected function collectFromGA4(Brand $brand, Carbon $date, ?int $backfillDays = null): void
    {
        $last = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('metric', 'visitors')
            ->whereNull('dimension')
            ->max('date');

        [$start, $end] = self::collectionWindow($last ? Carbon::parse($last) : null, null, $backfillDays);

        $data = $this->ga4Service->getDailyData($brand, $start->toDateString(), $end->toDateString());

        if (empty($data['daily'])) {
            Log::warning('GA4 returned no daily rows', [
                'brand_id' => $brand->id, 'from' => $start->toDateString(), 'to' => $end->toDateString(),
            ]);
        }

        $baseUrl = $brand->base_url;
        if (!$baseUrl) {
            Log::warning('AnalyticsCollector: brand has no website_url, top pages will lack full URLs', [
                'brand_id' => $brand->id,
            ]);
        }

        $meta = ['method' => 'ga4_daily'];

        foreach ($data['daily'] as $day => $m) {
            $d = Carbon::parse($day);
            $this->storeSnapshot($brand, $d, 'ga4', 'visitors', null, $m['activeUsers'] ?? 0, $meta);
            $this->storeSnapshot($brand, $d, 'ga4', 'new_users', null, $m['newUsers'] ?? 0, $meta);
            $this->storeSnapshot($brand, $d, 'ga4', 'sessions', null, $m['sessions'] ?? 0, $meta);
            $this->storeSnapshot($brand, $d, 'ga4', 'conversions', null, $m['keyEvents'] ?? 0, $meta);
            $this->storeSnapshot($brand, $d, 'ga4', 'revenue', null, $m['totalRevenue'] ?? 0, $meta);
            $this->storeSnapshot($brand, $d, 'ga4', 'page_views', null, $m['screenPageViews'] ?? 0, $meta);
            $this->storeSnapshot($brand, $d, 'ga4', 'engagement_rate', null, round(($m['engagementRate'] ?? 0) * 100, 2), $meta);
            $this->storeSnapshot($brand, $d, 'ga4', 'avg_session_duration', null, round($m['averageSessionDuration'] ?? 0, 1), $meta);
        }

        // Per-page / per-channel rows are derived from the same window. Clear that window's
        // old rows first: earlier versions stored 30-day totals under a single date, which
        // would otherwise linger and inflate sums.
        if (!isset($data['errors']['pages'])) {
            $this->replaceDimensionRows($brand, $start, $end, ['visitors', 'conversions']);
            foreach ($data['pages'] as $day => $pages) {
                $d = Carbon::parse($day);
                foreach ($pages as $path => $m) {
                    $url = $this->buildFullUrl($baseUrl, $path);
                    $this->storeSnapshot($brand, $d, 'ga4', 'visitors', $url, $m['activeUsers'] ?? 0, $meta);
                    $this->storeSnapshot($brand, $d, 'ga4', 'conversions', $url, $m['keyEvents'] ?? 0, $meta);
                }
            }
        }

        if (!isset($data['errors']['channels'])) {
            $this->replaceDimensionRows($brand, $start, $end, ['channel_visitors', 'channel_sessions', 'channel_conversions']);
            foreach ($data['channels'] as $day => $channels) {
                $d = Carbon::parse($day);
                foreach ($channels as $channel => $m) {
                    $this->storeSnapshot($brand, $d, 'ga4', 'channel_visitors', $channel, $m['activeUsers'] ?? 0, $meta);
                    $this->storeSnapshot($brand, $d, 'ga4', 'channel_sessions', $channel, $m['sessions'] ?? 0, $meta);
                    $this->storeSnapshot($brand, $d, 'ga4', 'channel_conversions', $channel, $m['keyEvents'] ?? 0, $meta);
                }
            }
        }

        if ($data['errors']) {
            Log::warning('GA4 partial collection', ['brand_id' => $brand->id, 'errors' => $data['errors']]);
        }

        Log::info('GA4 daily data stored', [
            'brand_id' => $brand->id,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'days' => count($data['daily']),
        ]);
    }

    protected function replaceDimensionRows(Brand $brand, Carbon $from, Carbon $to, array $metrics): void
    {
        $q = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->whereIn('metric', $metrics)
            ->whereNotNull('dimension')
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString());

        $q->delete();
    }

    /**
     * Build a full URL from a base URL and path.
     *
     * If $baseUrl is null, returns the path unchanged. Callers should
     * treat the result as "path-only" in that case — never construct
     * a fake host from the brand slug.
     */
    protected function buildFullUrl(?string $baseUrl, string $path): string
    {
        // If the path is already absolute, nothing to do
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // No base URL available — return the raw path.
        // Downstream code must handle paths without hosts.
        if (empty($baseUrl)) {
            return $path;
        }

        // Remove leading slash from path for proper joining
        $path = ltrim($path, '/');

        // Build the full URL
        $fullUrl = $baseUrl . '/' . $path;

        // Normalize: collapse double slashes but not in the protocol
        $fullUrl = preg_replace('/(?<!:)\/+/', '/', $fullUrl);

        // Force HTTPS on the host portion only
        $fullUrl = preg_replace('#^http://#i', 'https://', $fullUrl);

        return $fullUrl;
    }

    /**
     * Store a snapshot in the database.
     */
    protected function storeSnapshot(Brand $brand, Carbon $date, string $source, string $metric, ?string $dimension, $value, ?array $metadata = null): void
    {
        try {
            AnalyticsSnapshot::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'date' => $date->toDateString(),
                    'source' => $source,
                    'metric' => $metric,
                    'dimension' => $dimension,
                ],
                array_filter(['value' => $value, 'metadata' => $metadata], fn ($v) => $v !== null)
            );
        } catch (\Exception $e) {
            Log::error('Failed to store snapshot', [
                'brand_id' => $brand->id,
                'source' => $source,
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
