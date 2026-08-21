<?php

namespace App\Services\Ahrefs;

use App\Models\Brand;
use App\Models\AhrefsBacklink;
use App\Models\AhrefsKeyword;
use App\Models\AhrefsSiteStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AhrefsService
{
    protected ?string $apiToken;
    protected string $apiEndpoint;

    public function __construct()
    {
        $this->apiToken = config('services.ahrefs.token', env('AHREFS_API_TOKEN'));
        $this->apiEndpoint = rtrim(config('services.ahrefs.endpoint', env('AHREFS_API_ENDPOINT', 'https://api.ahrefs.com/v3')), '/');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiToken);
    }

    /**
     * Collect Ahrefs data for a brand.
     */
    public function collectForBrand(Brand $brand): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Ahrefs API token not configured'];
        }

        // Get the website URL from the brand
        $websiteUrl = $brand->website_url ?? $brand->config['website_url'] ?? null;

        if (!$websiteUrl) {
            return ['error' => 'No website URL configured for this brand. Please add a website URL in Brand Settings.'];
        }

        $domain = $this->extractDomain($websiteUrl);

        Log::info('Collecting Ahrefs data for brand', [
            'brand_id' => $brand->id,
            'brand_name' => $brand->name,
            'domain' => $domain,
        ]);

        $results = [];

        try {
            // 1. Get site stats
            $results['site_stats'] = $this->fetchSiteStats($domain);
            $this->storeSiteStats($brand, $domain, $results['site_stats']);

            // 2. Get backlinks
            $results['backlinks'] = $this->fetchBacklinks($domain);
            $this->storeBacklinks($brand, $domain, $results['backlinks']);

            // 3. Get keyword rankings
            $results['keywords'] = $this->fetchKeywords($domain);
            $this->storeKeywords($brand, $domain, $results['keywords']);

            Log::info('Ahrefs data collected successfully', [
                'brand_id' => $brand->id,
                'domain' => $domain,
            ]);

        } catch (\Exception $e) {
            Log::error('Ahrefs data collection failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Get the target domain for a brand.
     */
    public function getTargetDomain(Brand $brand): ?string
    {
        $websiteUrl = $brand->website_url ?? $brand->config['website_url'] ?? null;

        if (!$websiteUrl) {
            return null;
        }

        return $this->extractDomain($websiteUrl);
    }
    /**
     * Fetch site stats from Ahrefs.
     */
    protected function fetchSiteStats(string $domain): array
    {
        try {
            $response = Http::withToken($this->apiToken)
            ->timeout(60)
            ->get("{$this->apiEndpoint}/site-explorer/domain-rating", [
                'target' => $domain,
                'date' => Carbon::today()->format('Y-m-d'),
            ]);

            if ($response->failed()) {
                $this->handleApiError($response);
            }

            $data = $response->json();

            // Log successful response
            Log::info('Ahrefs Site Stats API success', [
                'domain' => $domain,
                'has_data' => isset($data['domain_rating']),
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::warning('Ahrefs Site Stats API failed, using fallback', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            // Return default data structure
            return [
                'domain_rating' => [
                    'domain_rating' => 0,
                    'ahrefs_rank' => 0,
                ],
                'metrics' => [
                    'backlinks' => 0,
                    'refdomains' => 0,
                    'keywords' => 0,
                    'organic_traffic' => 0,
                ],
            ];
        }
    }

    /**
     * Fetch backlinks from Ahrefs.
     */
    protected function fetchBacklinks(string $domain): array
    {
        try {
            $response = Http::withToken($this->apiToken)
            ->timeout(60)
            ->get("{$this->apiEndpoint}/site-explorer/all-backlinks", [
                'target' => $domain,
                'limit' => 50,
                'select' => 'url_from,url_to,anchor,title,domain_rating_source,is_nofollow',
            ]);

            if ($response->failed()) {
                $this->handleApiError($response);
            }

            $data = $response->json();

            Log::info('Ahrefs Backlinks API success', [
                'domain' => $domain,
                'count' => count($data['backlinks'] ?? []),
            ]);

            return $data['backlinks'] ?? [];
        } catch (\Exception $e) {
            Log::warning('Ahrefs Backlinks API failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Fetch keyword rankings from Ahrefs.
     */
    protected function fetchKeywords(string $domain): array
    {
        try {
            $response = Http::withToken($this->apiToken)
            ->timeout(60)
            ->get("{$this->apiEndpoint}/site-explorer/organic-keywords", [
                'target' => $domain,
                'limit' => 50,
                'select' => 'keyword,position,volume,kd,best_url',
            ]);

            if ($response->failed()) {
                $this->handleApiError($response);
            }

            $data = $response->json();

            Log::info('Ahrefs Keywords API success', [
                'domain' => $domain,
                'count' => count($data['keywords'] ?? []),
            ]);

            return $data['keywords'] ?? [];
        } catch (\Exception $e) {
            Log::warning('Ahrefs Keywords API failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Store site stats in database.
     */
    protected function storeSiteStats(Brand $brand, string $domain, array $data): void
    {
        $domainRatingData = $data['domain_rating'] ?? [];
        $metrics = $data['metrics'] ?? [];

        AhrefsSiteStat::create([
            'brand_id' => $brand->id,
            'domain' => $domain,
            'domain_rating' => $domainRatingData['domain_rating'] ?? null,
            'url_rating' => $domainRatingData['ahrefs_rank'] ?? null,
            'backlinks' => $metrics['backlinks'] ?? 0,
            'referring_domains' => $metrics['refdomains'] ?? 0,
            'organic_keywords' => $metrics['keywords'] ?? 0,
            'organic_traffic' => $metrics['organic_traffic'] ?? null,
            'traffic_value' => $metrics['traffic_value'] ?? null,
            'tracked_date' => Carbon::today(),
                               'metadata' => $data,
        ]);
    }

    /**
     * Store backlinks in database.
     */
    protected function storeBacklinks(Brand $brand, string $domain, array $backlinks): void
    {
        foreach ($backlinks as $backlink) {
            $sourceUrl = $backlink['url_from'] ?? '';
            $targetUrl = $backlink['url_to'] ?? $domain;

            AhrefsBacklink::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'source_url' => $sourceUrl,
                    'target_url' => $targetUrl,
                ],
                [
                    'anchor_text' => $backlink['anchor'] ?? null,
                    'source_domain' => parse_url($sourceUrl, PHP_URL_HOST) ?? '',
                                           'source_domain_rating' => $backlink['domain_rating_source'] ?? null,
                                           'source_page_title' => $backlink['title'] ?? null,
                                           'is_nofollow' => $backlink['is_nofollow'] ?? false,
                                           'is_follow' => !($backlink['is_nofollow'] ?? false),
                                           'first_seen_at' => $backlink['first_seen'] ?? null,
                                           'last_seen_at' => $backlink['last_seen'] ?? null,
                                           'metadata' => $backlink,
                ]
            );
        }
    }

    /**
     * Store keywords in database.
     */
    protected function storeKeywords(Brand $brand, string $domain, array $keywords): void
    {
        foreach ($keywords as $keyword) {
            AhrefsKeyword::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'keyword' => $keyword['keyword'] ?? '',
                    'tracked_date' => Carbon::today(),
                ],
                [
                    'target_url' => $keyword['best_url'] ?? null,
                    'position' => $keyword['position'] ?? null,
                    'search_volume' => $keyword['volume'] ?? null,
                    'difficulty' => $keyword['kd'] ?? null,
                    'metadata' => $keyword,
                ]
            );
        }
    }

    /**
     * Extract domain from URL.
     */
    protected function extractDomain(string $url): string
    {
        // Add scheme if missing
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        return preg_replace('/^www\./i', '', $host);
    }

    /**
     * Handle API errors with informative messages.
     */
    protected function handleApiError($response): void
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 401 || $status === 403) {
            throw new \Exception("Ahrefs API Authorization Failed (Status {$status}). Please check your API token.");
        }

        if ($status === 429) {
            throw new \Exception("Ahrefs API rate limit exceeded. Please wait and try again.");
        }

        throw new \Exception("Ahrefs API Error [{$status}]: {$body}");
    }

    /**
     * Get Ahrefs dashboard data for a brand.
     */
    public function getDashboardData(Brand $brand): array
    {
        $latestStats = AhrefsSiteStat::where('brand_id', $brand->id)
        ->orderBy('tracked_date', 'desc')
        ->first();

        $recentBacklinks = AhrefsBacklink::where('brand_id', $brand->id)
        ->orderBy('last_seen_at', 'desc')
        ->limit(10)
        ->get();

        $topKeywords = AhrefsKeyword::where('brand_id', $brand->id)
        ->where('tracked_date', Carbon::today())
        ->orderBy('position')
        ->limit(20)
        ->get();

        return [
            'site_stats' => $latestStats,
            'recent_backlinks' => $recentBacklinks,
            'top_keywords' => $topKeywords,
            'backlinks_count' => AhrefsBacklink::where('brand_id', $brand->id)->count(),
            'keywords_count' => AhrefsKeyword::where('brand_id', $brand->id)
            ->where('tracked_date', Carbon::today())
            ->count(),
        ];
    }
}
