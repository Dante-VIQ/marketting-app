<?php

namespace App\Services\Analytics;

/**
 * Pure parsers for GA4 Data API runReport responses.
 * Metrics are read by header NAME (never by position) so reordering a request cannot
 * silently shuffle numbers between metrics.
 */
class GA4ReportParser
{
    public const TOP_PAGES_PER_DAY = 25;

    /** "20261017" -> "2026-10-17" */
    public static function date(string $ga4Date): string
    {
        return substr($ga4Date, 0, 4) . '-' . substr($ga4Date, 4, 2) . '-' . substr($ga4Date, 6, 2);
    }

    /** One row per date: [date => [metricName => float]] */
    public static function daily(array $response): array
    {
        $out = [];
        foreach (self::rows($response) as [$dims, $metrics]) {
            $out[self::date($dims[0])] = $metrics;
        }
        ksort($out);

        return $out;
    }

    /** [date => [pagePath => [metricName => float]]], top N pages per day by activeUsers. */
    public static function pages(array $response, int $perDay = self::TOP_PAGES_PER_DAY): array
    {
        $out = [];
        foreach (self::rows($response) as [$dims, $metrics]) {
            $out[self::date($dims[0])][$dims[1] ?? '/'] = $metrics;
        }

        foreach ($out as $date => $pages) {
            uasort($pages, fn ($a, $b) => ($b['activeUsers'] ?? 0) <=> ($a['activeUsers'] ?? 0));
            $out[$date] = array_slice($pages, 0, $perDay, true);
        }
        ksort($out);

        return $out;
    }

    /** [date => [channelName => [metricName => float]]] */
    public static function channels(array $response): array
    {
        $out = [];
        foreach (self::rows($response) as [$dims, $metrics]) {
            $out[self::date($dims[0])][$dims[1] ?? '(not set)'] = $metrics;
        }
        ksort($out);

        return $out;
    }

    /** @return array<int, array{0: string[], 1: array<string, float>}> */
    private static function rows(array $response): array
    {
        $names = array_map(fn ($h) => $h['name'] ?? '', $response['metricHeaders'] ?? []);
        $rows  = [];

        foreach ($response['rows'] ?? [] as $row) {
            $dims = array_map(fn ($d) => (string) ($d['value'] ?? ''), $row['dimensionValues'] ?? []);
            if (($dims[0] ?? '') === '') {
                continue;
            }

            $metrics = [];
            foreach ($row['metricValues'] ?? [] as $i => $m) {
                if (isset($names[$i])) {
                    $metrics[$names[$i]] = (float) ($m['value'] ?? 0);
                }
            }
            $rows[] = [$dims, $metrics];
        }

        return $rows;
    }
}
