<?php

namespace App\Services\Bing;

use App\Models\Brand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * BingWebmasterService
 *
 * Fetches data from the Bing Webmaster Tools REST API using an API key
 * stored in the brand's settings JSON column under key "bing_api_key".
 *
 * API key is never committed to code or env — it lives only in the DB
 * brand settings record, editable from the brand settings UI.
 *
 * All endpoints degrade gracefully: if any single endpoint fails, the
 * rest still return. Missing data is returned as empty arrays so callers
 * don't need to null-check every key.
 *
 * Base URL: https://ssl.bing.com/webmaster/api.svc/json
 */
class BingWebmasterService
{
    private const BASE_URL = 'https://ssl.bing.com/webmaster/api.svc/json';

    /**
     * Fetch the complete Bing data bundle for a brand's registered site.
     *
     * Returns all available data with graceful degradation per endpoint.
     */
    public function fetchAll(Brand $brand): array
    {
        $apiKey = $this->getApiKey($brand);
        if (!$apiKey) {
            return $this->emptyBundle('No Bing API key configured for this brand.');
        }

        $siteUrl = $brand->website_url;
        if (!$siteUrl) {
            return $this->emptyBundle('No website URL set for this brand.');
        }

        // Cache for 6 hours — Bing data doesn't change minute-to-minute
        $cacheKey = "bing_data_{$brand->id}_" . md5($siteUrl);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($apiKey, $siteUrl, $brand) {
            return $this->fetchFresh($apiKey, $siteUrl, $brand);
        });
    }

    /**
     * Force-refresh the cache and fetch fresh data.
     */
    public function refresh(Brand $brand): array
    {
        $siteUrl = $brand->website_url;
        $cacheKey = "bing_data_{$brand->id}_" . md5($siteUrl ?? '');
        Cache::forget($cacheKey);

        return $this->fetchAll($brand);
    }

    /**
     * Get just the keyword gaps (high impressions, low position) for content planning.
     * Returns up to $limit keyword gap opportunities sorted by impressions desc.
     */
    public function getKeywordGaps(Brand $brand, int $limit = 30): array
    {
        $data = $this->fetchAll($brand);
        return $data['keyword_gaps'] ?? [];
    }

    /**
     * Get real Bing search queries for use as AI visibility probes.
     * Returns actual user queries that drove impressions on the site.
     */
    public function getRealQueries(Brand $brand, int $limit = 20): array
    {
        $data = $this->fetchAll($brand);
        $queries = $data['search_performance']['queries'] ?? [];

        return array_slice(
            array_map(fn($q) => $q['query'] ?? null, $queries),
            0,
            $limit
        );
    }

    // ─── Private: fetch pipeline ─────────────────────────────────────────────

    private function fetchFresh(string $apiKey, string $siteUrl, Brand $brand): array
    {
        $results = [
            'site_url'           => $siteUrl,
            'fetched_at'         => now()->toIso8601String(),
            'search_performance' => [],
            'keyword_research'   => [],
            'top_insights'       => [],
            'backlinks'          => [],
            'pages'              => [],
            'keyword_gaps'       => [],
            'errors'             => [],
        ];

        // ── 1. Search Performance (last 30 days) ──────────────────────────
        try {
            $perf = $this->fetchSearchPerformance($apiKey, $siteUrl);
            $results['search_performance'] = $perf;
        } catch (\Throwable $e) {
            $results['errors']['search_performance'] = $e->getMessage();
            Log::warning('BingWebmaster: search_performance failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
        }

        // ── 2. Keyword Research ────────────────────────────────────────────
        try {
            $kw = $this->fetchKeywordResearch($apiKey, $siteUrl);
            $results['keyword_research'] = $kw;
        } catch (\Throwable $e) {
            $results['errors']['keyword_research'] = $e->getMessage();
            Log::warning('BingWebmaster: keyword_research failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
        }

        // ── 3. Top Insights ───────────────────────────────────────────────
        try {
            $insights = $this->fetchTopInsights($apiKey, $siteUrl);
            $results['top_insights'] = $insights;
        } catch (\Throwable $e) {
            $results['errors']['top_insights'] = $e->getMessage();
            Log::warning('BingWebmaster: top_insights failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
        }

        // ── 4. Backlinks ──────────────────────────────────────────────────
        try {
            $links = $this->fetchBacklinks($apiKey, $siteUrl);
            $results['backlinks'] = $links;
        } catch (\Throwable $e) {
            $results['errors']['backlinks'] = $e->getMessage();
            Log::warning('BingWebmaster: backlinks failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
        }

        // ── 5. Pages & Indexing ───────────────────────────────────────────
        try {
            $pages = $this->fetchPages($apiKey, $siteUrl);
            $results['pages'] = $pages;
        } catch (\Throwable $e) {
            $results['errors']['pages'] = $e->getMessage();
            Log::warning('BingWebmaster: pages failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
        }

        // ── 6. Derive keyword gaps from search performance ─────────────────
        $results['keyword_gaps'] = $this->deriveKeywordGaps($results['search_performance']);

        return $results;
    }

    // ─── Private: individual API calls ───────────────────────────────────────

    /**
     * GetQueryStats — search performance for the site over the last 30 days.
     * Docs: GET /GetQueryStats?apikey=&siteUrl=&startDate=&endDate=&timegrain=Monthly
     */
    private function fetchSearchPerformance(string $apiKey, string $siteUrl): array
    {
        $endDate   = now()->format('Y-m-d');
        $startDate = now()->subDays(30)->format('Y-m-d');

        $response = Http::withOptions(['verify' => '/root/.ccr/ca-bundle.crt'])
            ->timeout(15)
            ->get(self::BASE_URL . '/GetQueryStats', [
                'apikey'    => $apiKey,
                'siteUrl'   => $siteUrl,
                'startDate' => $startDate,
                'endDate'   => $endDate,
            ]);

        if (!$response->successful()) {
            Log::debug('BingWebmaster search_performance raw', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException("Bing API error {$response->status()}: " . $response->body());
        }

        $data    = $response->json();
        $queries = [];

        foreach ($data['d'] ?? [] as $row) {
            $queries[] = [
                'query'       => $row['Query']       ?? '',
                'impressions' => $row['Impressions']  ?? 0,
                'clicks'      => $row['Clicks']       ?? 0,
                'position'    => $row['AvgPosition']  ?? 0,
                'ctr'         => $row['Ctr']          ?? 0,
            ];
        }

        // Sort by impressions descending
        usort($queries, fn($a, $b) => $b['impressions'] <=> $a['impressions']);

        return [
            'period'     => "{$startDate} to {$endDate}",
            'queries'    => $queries,
            'total_rows' => count($queries),
        ];
    }

    /**
     * GetRelatedKeywords — keyword suggestions for the site's main topics.
     * Docs: GET /GetRelatedKeywords?apikey=&query=&country=&language=
     */
    private function fetchKeywordResearch(string $apiKey, string $siteUrl): array
    {
        // Use the domain as the seed query
        $domain = parse_url($siteUrl, PHP_URL_HOST) ?? $siteUrl;
        $domain = preg_replace('/^www\./', '', $domain);

        $response = Http::withOptions(['verify' => '/root/.ccr/ca-bundle.crt'])
            ->timeout(15)
            ->get(self::BASE_URL . '/GetRelatedKeywords', [
                'apikey'   => $apiKey,
                'query'    => $domain,
                'country'  => 'US',
                'language' => 'en',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Bing API error {$response->status()}: " . $response->body());
        }

        $data     = $response->json();
        $keywords = [];

        foreach ($data['d'] ?? [] as $row) {
            $keywords[] = [
                'keyword'    => $row['Keyword']         ?? '',
                'volume'     => $row['ImpressionShare'] ?? 0,
                'broad_volume' => $row['BroadSearchImpressions'] ?? 0,
            ];
        }

        return [
            'keywords'    => $keywords,
            'total_rows'  => count($keywords),
        ];
    }

    /**
     * GetTopInsights — Bing's own content insights for the site.
     * Docs: GET /GetTopInsights?apikey=&siteUrl=
     */
    private function fetchTopInsights(string $apiKey, string $siteUrl): array
    {
        $response = Http::withOptions(['verify' => '/root/.ccr/ca-bundle.crt'])
            ->timeout(15)
            ->get(self::BASE_URL . '/GetTopInsights', [
                'apikey'  => $apiKey,
                'siteUrl' => $siteUrl,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Bing API error {$response->status()}: " . $response->body());
        }

        $data     = $response->json();
        $insights = [];

        foreach ($data['d'] ?? [] as $item) {
            $insights[] = [
                'title'       => $item['Title']       ?? '',
                'description' => $item['Description'] ?? '',
                'type'        => $item['InsightType']  ?? '',
                'category'    => $item['Category']     ?? '',
            ];
        }

        return $insights;
    }

    /**
     * GetLinkCounts — backlink summary for the site.
     * Docs: GET /GetLinkCounts?apikey=&siteUrl=
     */
    private function fetchBacklinks(string $apiKey, string $siteUrl): array
    {
        $response = Http::withOptions(['verify' => '/root/.ccr/ca-bundle.crt'])
            ->timeout(15)
            ->get(self::BASE_URL . '/GetLinkCounts', [
                'apikey'  => $apiKey,
                'siteUrl' => $siteUrl,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Bing API error {$response->status()}: " . $response->body());
        }

        $data = $response->json();
        $d    = $data['d'] ?? [];

        return [
            'total_pages_with_links' => $d['PagesWithExternalLinks'] ?? 0,
            'total_backlinks'        => $d['InboundLinks']            ?? 0,
            'total_linking_domains'  => $d['InboundLinkingDomains']  ?? 0,
        ];
    }

    /**
     * GetUrlInfo — pages and indexing status.
     * Docs: GET /GetUrlInfo?apikey=&siteUrl=&page=0
     */
    private function fetchPages(string $apiKey, string $siteUrl): array
    {
        $response = Http::withOptions(['verify' => '/root/.ccr/ca-bundle.crt'])
            ->timeout(15)
            ->get(self::BASE_URL . '/GetUrlInfo', [
                'apikey'  => $apiKey,
                'siteUrl' => $siteUrl,
                'page'    => 0,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Bing API error {$response->status()}: " . $response->body());
        }

        $data  = $response->json();
        $pages = [];

        foreach ($data['d'] ?? [] as $row) {
            $pages[] = [
                'url'           => $row['Url']              ?? '',
                'indexed'       => $row['IsIndexed']        ?? false,
                'last_crawled'  => $row['LastCrawledTime']  ?? null,
                'http_code'     => $row['HttpCode']         ?? null,
            ];
        }

        return [
            'pages'      => $pages,
            'total_rows' => count($pages),
        ];
    }

    // ─── Private: keyword gap derivation ─────────────────────────────────────

    /**
     * Derive keyword gap opportunities from search performance data.
     *
     * "Gap" = queries where the site is getting impressions but ranking poorly
     * (position > 5) or getting impressions but very low CTR. These are prime
     * content improvement / new content targets.
     */
    private function deriveKeywordGaps(array $searchPerf): array
    {
        $queries = $searchPerf['queries'] ?? [];
        $gaps    = [];

        foreach ($queries as $q) {
            $impressions = (int)   ($q['impressions'] ?? 0);
            $position    = (float) ($q['position']    ?? 0);
            $clicks      = (int)   ($q['clicks']      ?? 0);
            $query       = trim($q['query'] ?? '');

            if (!$query || $impressions < 10) {
                continue;
            }

            // Gap criteria:
            // - Ranking 6–30 (visible but not competitive) with decent impressions, OR
            // - Ranking 1–5 but very low CTR (< 2%) suggesting title/meta mismatch
            $isLowRankHighImpression = $position > 5 && $position <= 30 && $impressions >= 50;
            $isHighImpLowCtr         = $position <= 5 && ($q['ctr'] ?? 0) < 0.02 && $impressions >= 100;

            if ($isLowRankHighImpression || $isHighImpLowCtr) {
                $gaps[] = [
                    'query'       => $query,
                    'impressions' => $impressions,
                    'position'    => round($position, 1),
                    'clicks'      => $clicks,
                    'ctr'         => round(($q['ctr'] ?? 0) * 100, 2),
                    'gap_type'    => $isHighImpLowCtr ? 'ctr_mismatch' : 'ranking_gap',
                    'opportunity' => $this->scoreOpportunity($impressions, $position),
                ];
            }
        }

        // Sort by opportunity score descending
        usort($gaps, fn($a, $b) => $b['opportunity'] <=> $a['opportunity']);

        return array_slice($gaps, 0, 30);
    }

    /**
     * Score an opportunity from 0–100 for ranking in content priority lists.
     */
    private function scoreOpportunity(int $impressions, float $position): int
    {
        // Higher impressions = more potential traffic
        // Lower position (closer to 1) = less improvement needed but already ranking
        // We want: high impressions AND poor ranking = highest score
        $impressionScore = min(50, $impressions / 10);
        $positionScore   = min(50, $position * 1.5);

        return (int) ($impressionScore + $positionScore);
    }

    // ─── Private: helpers ────────────────────────────────────────────────────

    private function getApiKey(Brand $brand): ?string
    {
        $settings = $brand->settings ?? [];
        return $settings['bing_api_key'] ?? null;
    }

    private function emptyBundle(string $reason): array
    {
        return [
            'site_url'           => null,
            'fetched_at'         => now()->toIso8601String(),
            'search_performance' => [],
            'keyword_research'   => [],
            'top_insights'       => [],
            'backlinks'          => [],
            'pages'              => [],
            'keyword_gaps'       => [],
            'errors'             => ['config' => $reason],
        ];
    }
}
