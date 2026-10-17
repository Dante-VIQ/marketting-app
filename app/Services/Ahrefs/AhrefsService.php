<?php

namespace App\Services\Ahrefs;

use App\Models\Brand;
use App\Models\AhrefsBacklink;
use App\Models\AhrefsKeyword;
use App\Models\AhrefsSiteStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AhrefsService
{
    protected ?string $apiToken;
    protected string $apiEndpoint;

    protected AhrefsApi $api;

    public function __construct(?AhrefsApi $api = null)
    {
        $this->apiToken = config('services.ahrefs.token', env('AHREFS_API_TOKEN'));
        $this->apiEndpoint = rtrim(config('services.ahrefs.endpoint', env('AHREFS_API_ENDPOINT', 'https://api.ahrefs.com/v3')), '/');
        $this->api = $api ?? new AhrefsApi();
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiToken);
    }

    /**
     * Collect Ahrefs data for a brand.
     *
     * Each section (site stats, keywords, backlinks) is fetched and stored
     * independently. A failed section is reported in `errors` and stores NOTHING —
     * never zeros — so history only contains values Ahrefs actually returned.
     */
    public function collectForBrand(Brand $brand): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Ahrefs API token not configured'];
        }

        $domain = $this->getTargetDomain($brand);
        if (!$domain) {
            return ['error' => 'No website URL configured for this brand. Please add a website URL in Brand Settings.'];
        }

        $country = $brand->config['ahrefs_country'] ?? null;   // e.g. "KE"; null = all countries
        $today   = Carbon::today();
        $date    = $today->format('Y-m-d');
        $compare = $today->copy()->subDays(30)->format('Y-m-d');

        Log::info('Collecting Ahrefs data for brand', ['brand_id' => $brand->id, 'domain' => $domain]);

        $results = ['collected' => [], 'errors' => []];

        $sections = [
            'site_stats' => fn () => $this->storeSiteStats($brand, $domain, $this->api->siteStats($domain, $date, $country)),
            'keywords'   => fn () => $this->storeKeywords($brand, $this->api->keywords(
                $domain, $date, $compare, (int) config('services.ahrefs.keyword_limit', 100), $country
            )),
            'backlinks'  => fn () => $this->storeBacklinks($brand, $domain, $this->api->backlinks(
                $domain, $compare, (int) config('services.ahrefs.backlink_limit', 100)
            )),
        ];

        foreach ($sections as $name => $run) {
            try {
                $results['collected'][$name] = $run();
            } catch (\Throwable $e) {
                $results['errors'][$name] = $e->getMessage();
                Log::error("Ahrefs {$name} collection failed", [
                    'brand_id' => $brand->id, 'domain' => $domain, 'error' => $e->getMessage(),
                ]);
            }
        }

        if ($results['errors']) {
            $results['error'] = implode(' | ', array_map(
                fn ($k, $v) => "{$k}: {$v}", array_keys($results['errors']), $results['errors']
            ));
        }

        return $results;
    }

    /**
     * Get the target domain for a brand.
     */
    public function getTargetDomain(Brand $brand): ?string
    {
        $websiteUrl = $brand->website_url ?? $brand->config['website_url'] ?? null;

        return $websiteUrl ? $this->extractDomain($websiteUrl) : null;
    }

    protected function storeSiteStats(Brand $brand, string $domain, array $stats): int
    {
        AhrefsSiteStat::updateOrCreate(
            ['brand_id' => $brand->id, 'domain' => $domain, 'tracked_date' => Carbon::today()],
            [
                'domain_rating'     => $stats['domain_rating'] !== null ? (int) round($stats['domain_rating']) : null,
                'url_rating'        => null, // not provided by domain-rating; ahrefs_rank is kept in metadata
                'backlinks'         => $stats['backlinks'],
                'referring_domains' => $stats['referring_domains'],
                'organic_keywords'  => $stats['organic_keywords'],
                'keywords_top3'     => $stats['keywords_top3'],
                'organic_traffic'   => $stats['organic_traffic'],
                'traffic_value'     => $stats['traffic_value'],
                'metadata'          => $stats,
            ]
        );

        return 1;
    }

    protected function storeKeywords(Brand $brand, array $keywords): int
    {
        $stored = 0;
        foreach ($keywords as $k) {
            if ($k['keyword'] === '') {
                continue;
            }

            AhrefsKeyword::updateOrCreate(
                ['brand_id' => $brand->id, 'keyword' => $k['keyword'], 'tracked_date' => Carbon::today()],
                [
                    'target_url'        => $k['url'],
                    'position'          => $k['position'],
                    'previous_position' => $k['previous_position'],
                    'position_change'   => $k['position_change'],
                    'search_volume'     => $k['volume'],
                    'difficulty'        => $k['difficulty'] !== null ? (string) $k['difficulty'] : null,
                    'traffic'           => $k['traffic'],
                    'intent'            => $k['intent'],
                    'is_branded'        => $k['is_branded'],
                    'metadata'          => $k['raw'],
                ]
            );
            $stored++;
        }

        return $stored;
    }

    protected function storeBacklinks(Brand $brand, string $domain, array $backlinks): int
    {
        $stored = 0;
        foreach ($backlinks as $b) {
            if ($b['source_url'] === '') {
                continue;
            }

            AhrefsBacklink::updateOrCreate(
                [
                    'brand_id'   => $brand->id,
                    'source_url' => $b['source_url'],
                    'target_url' => $b['target_url'] ?: $domain,
                ],
                [
                    'anchor_text'          => $b['anchor'],
                    'source_domain'        => parse_url($b['source_url'], PHP_URL_HOST) ?: '',
                    'source_domain_rating' => $b['source_dr'] !== null ? (string) $b['source_dr'] : null,
                    'source_page_title'    => $b['title'],
                    'is_nofollow'          => $b['is_nofollow'],
                    'is_follow'            => $b['is_follow'],
                    'first_seen_at'        => $b['first_seen'],
                    'last_seen_at'         => $b['last_seen'],
                    'is_lost'              => $b['is_lost'],
                    'lost_reason'          => $b['lost_reason'],
                    'is_spam'              => $b['is_spam'],
                    'metadata'             => $b['raw'],
                ]
            );
            $stored++;
        }

        return $stored;
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
