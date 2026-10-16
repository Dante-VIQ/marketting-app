<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSnapshot;
use Carbon\Carbon;

/**
 * Reads analytics_snapshots (long format: one row per source/metric/dimension/date)
 * and turns them into the numbers the agent and its alerts need.
 *
 * Rows with a NULL dimension are the brand-level daily value of a metric.
 * Rows with a dimension are per-page (visitors) or per-channel/device/country breakdowns.
 */
class AnalyticsSummaryService
{
    public const METRICS = ['visitors', 'sessions', 'page_views', 'conversions', 'revenue'];

    /** Trend thresholds (relative change vs the previous window). */
    private const TREND_UP = 10.0;
    private const TREND_DOWN = -10.0;
    private const DROP_ALERT = -30.0;
    private const MIN_SESSIONS_FOR_ZERO_CONVERSION_ALERT = 100;

    public function latestDate(int $brandId, string $source = 'ga4'): ?Carbon
    {
        $date = AnalyticsSnapshot::where('brand_id', $brandId)
            ->where('source', $source)
            ->whereNull('dimension')
            ->max('date');

        return $date ? Carbon::parse($date) : null;
    }

    /**
     * Current vs previous window summary. Windows end at the latest day with data,
     * so a stale collector shows old-but-real numbers with `stale_days` set,
     * instead of zeros.
     */
    public function summary(int $brandId, int $windowDays = 7): array
    {
        $asOf = $this->latestDate($brandId);

        if (!$asOf) {
            return ['has_data' => false];
        }

        $currentStart  = $asOf->copy()->subDays($windowDays - 1);
        $previousEnd   = $currentStart->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($windowDays - 1);

        $current  = $this->windowTotals($brandId, $currentStart, $asOf);
        $previous = $this->windowTotals($brandId, $previousStart, $previousEnd);

        $changes = [];
        foreach (self::METRICS as $metric) {
            $changes[$metric] = $this->pctChange($current['totals'][$metric], $previous['totals'][$metric], $previous['days_with_data']);
        }

        $currentRate  = $this->conversionRate($current['totals']);
        $previousRate = $this->conversionRate($previous['totals']);

        return [
            'has_data'        => true,
            'as_of'           => $asOf->toDateString(),
            'stale_days'      => max(Carbon::today()->diffInDays($asOf, false) * -1, 0),
            'window_days'     => $windowDays,
            'current'         => $current['totals'],
            'previous'        => $previous['totals'],
            'days_with_data'  => $current['days_with_data'],
            'previous_days_with_data' => $previous['days_with_data'],
            'changes_pct'     => $changes,
            'conversion_rate' => $currentRate,
            'previous_conversion_rate' => $previousRate,
            'trend'           => $this->trend($changes['conversions']),
            'top_pages'       => $this->topPages($brandId, $currentStart, $asOf),
        ];
    }

    /**
     * A conversion alert only when the data supports it:
     * a real drop vs the previous window, or many sessions and no conversions.
     * No data / not enough history = no alert (never "low conversions" from null).
     */
    public function conversionAlert(array $summary): ?array
    {
        if (empty($summary['has_data'])) {
            return null;
        }

        $conv   = (float) $summary['current']['conversions'];
        $prev   = (float) $summary['previous']['conversions'];
        $change = $summary['changes_pct']['conversions'];

        if ($prev > 0 && $change !== null && $change <= self::DROP_ALERT) {
            return [
                'reason'      => 'conversion_drop',
                'title'       => 'Conversions Dropped',
                'description' => sprintf(
                    'Conversions fell %.0f%% over the last %d days (%s vs %s the period before).',
                    abs($change),
                    $summary['window_days'],
                    $this->fmt($conv),
                    $this->fmt($prev)
                ),
            ];
        }

        if ((float) $summary['current']['sessions'] >= self::MIN_SESSIONS_FOR_ZERO_CONVERSION_ALERT && $conv <= 0) {
            return [
                'reason'      => 'no_conversions',
                'title'       => 'No Conversions Despite Traffic',
                'description' => sprintf(
                    '%s sessions in the last %d days but no conversions recorded.',
                    $this->fmt((float) $summary['current']['sessions']),
                    $summary['window_days']
                ),
            ];
        }

        return null;
    }

    private function windowTotals(int $brandId, Carbon $from, Carbon $to): array
    {
        $rows = AnalyticsSnapshot::where('brand_id', $brandId)
            ->where('source', 'ga4')
            ->whereNull('dimension')
            ->whereIn('metric', self::METRICS)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get(['date', 'metric', 'value']);

        $totals = array_fill_keys(self::METRICS, 0.0);
        foreach ($rows as $row) {
            $totals[$row->metric] += (float) $row->value;
        }

        return [
            'totals'         => array_map(fn($v) => round($v, 2), $totals),
            'days_with_data' => $rows->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->unique()->count(),
        ];
    }

    private function topPages(int $brandId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return AnalyticsSnapshot::where('brand_id', $brandId)
            ->where('source', 'ga4')
            ->where('metric', 'visitors')
            ->whereNotNull('dimension')
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->select('dimension')
            ->selectRaw('SUM(value) as total_visitors')
            ->groupBy('dimension')
            ->orderByDesc('total_visitors')
            ->limit($limit)
            ->get()
            ->map(fn($r) => ['path' => $r->dimension, 'visitors' => (float) $r->total_visitors])
            ->all();
    }

    private function pctChange(float $current, float $previous, int $previousDays): ?float
    {
        if ($previousDays === 0 || $previous <= 0) {
            return null; // no baseline: unknown, not 0%
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function conversionRate(array $totals): ?float
    {
        return $totals['sessions'] > 0 ? round(($totals['conversions'] / $totals['sessions']) * 100, 2) : null;
    }

    private function trend(?float $conversionsChange): string
    {
        if ($conversionsChange === null) {
            return 'unknown';
        }

        return $conversionsChange >= self::TREND_UP ? 'up' : ($conversionsChange <= self::TREND_DOWN ? 'down' : 'stable');
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
