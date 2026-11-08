<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\PageSnapshot;
use App\Services\Bing\BingWebmasterService;

class BrandContextService
{
    public function __construct(
        private readonly BingWebmasterService $bing,
    ) {}

    /**
     * Get the full brand context for AI prompts.
     *
     * Enriches base brand fields with data from:
     * - Completed page scanner snapshots (page-level AEO data)
     * - Bing Webmaster Tools (search performance, keyword gaps, real queries)
     */
    public function getFullContext(Brand $brand): array
    {
        $base = [
            'id'           => $brand->id,
            'name'         => $brand->name,
            'slug'         => $brand->slug,
            'domain_type'  => $brand->domain_type,
            'brand_voice'  => $brand->brand_voice,
            'timezone'     => $brand->timezone,
            'config'       => $brand->config,
            'settings'     => $brand->settings,
            'domain_label' => $this->getDomainLabel($brand->domain_type),
            'domain_icon'  => $this->getDomainIcon($brand->domain_type),
        ];

        $pageContext = $this->getPageScannerContext($brand);
        $bingContext = $this->getBingContext($brand);

        return array_merge($base, $pageContext, $bingContext);
    }

    /**
     * Get Bing Webmaster context for AI prompts.
     *
     * Returns keyword gaps, top-performing queries, and insights.
     * Returns empty arrays gracefully when no API key is configured.
     */
    public function getBingContext(Brand $brand): array
    {
        try {
            $data = $this->bing->fetchAll($brand);

            // Don't surface raw credentials or large raw datasets in the context bundle.
            // Extract only the AI-relevant summaries.
            $queries   = $data['search_performance']['queries']   ?? [];
            $gaps      = $data['keyword_gaps']                    ?? [];
            $insights  = $data['top_insights']                    ?? [];
            $backlinks = $data['backlinks']                       ?? [];

            // Top 10 queries by impressions (actual user searches)
            $topQueries = array_slice(
                array_map(fn($q) => [
                    'query'       => $q['query'],
                    'impressions' => $q['impressions'],
                    'position'    => $q['position'],
                ], $queries),
                0, 10
            );

            // Top 15 keyword gaps (high impressions, low rank = content opportunity)
            $topGaps = array_slice(
                array_map(fn($g) => [
                    'query'       => $g['query'],
                    'impressions' => $g['impressions'],
                    'position'    => $g['position'],
                    'gap_type'    => $g['gap_type'],
                ], $gaps),
                0, 15
            );

            return [
                'bing_top_queries'    => $topQueries,
                'bing_keyword_gaps'   => $topGaps,
                'bing_insights'       => array_slice($insights, 0, 5),
                'bing_backlinks'      => $backlinks,
                'bing_has_data'       => !empty($topQueries) || !empty($topGaps),
                'bing_fetched_at'     => $data['fetched_at'] ?? null,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('BrandContextService: Bing context failed', [
                'brand_id' => $brand->id,
                'error'    => $e->getMessage(),
            ]);

            return [
                'bing_top_queries'  => [],
                'bing_keyword_gaps' => [],
                'bing_insights'     => [],
                'bing_backlinks'    => [],
                'bing_has_data'     => false,
                'bing_fetched_at'   => null,
            ];
        }
    }

    /**
     * Build a brand-context summary from completed page scanner snapshots.
     *
     * Returns up to 10 pages worth of metadata — titles, descriptions, headings,
     * and top topics — plus aggregate word counts and detected page types.
     * This is intentionally lightweight (no raw HTML) so it fits in an AI prompt.
     *
     * @return array{
     *   scanned_pages: array,
     *   page_types_found: array,
     *   all_topics: array,
     *   total_words_indexed: int,
     *   pages_scanned_count: int
     * }
     */
    public function getPageScannerContext(Brand $brand): array
    {
        $snapshots = PageSnapshot::where('brand_id', $brand->id)
            ->where('status', 'completed')
            ->orderByDesc('scraped_at')
            ->limit(10)
            ->get(['url', 'page_type', 'title', 'meta_description', 'headings', 'topics_covered', 'word_count']);

        if ($snapshots->isEmpty()) {
            return [
                'scanned_pages'        => [],
                'page_types_found'     => [],
                'all_topics'           => [],
                'total_words_indexed'  => 0,
                'pages_scanned_count'  => 0,
            ];
        }

        $pages         = [];
        $allTopics     = [];
        $pageTypes     = [];
        $totalWords    = 0;

        foreach ($snapshots as $snap) {
            $h1 = $snap->headings['h1'] ?? null;
            $h2 = array_slice($snap->headings['h2'] ?? [], 0, 3);

            $pages[] = [
                'url'         => $snap->url,
                'page_type'   => $snap->page_type,
                'title'       => $snap->title,
                'description' => $snap->meta_description,
                'h1'          => $h1,
                'h2_headings' => $h2,
                'topics'      => array_slice($snap->topics_covered ?? [], 0, 10),
                'word_count'  => $snap->word_count,
            ];

            if ($snap->page_type) {
                $pageTypes[] = $snap->page_type;
            }

            foreach (($snap->topics_covered ?? []) as $topic) {
                $allTopics[] = $topic;
            }

            $totalWords += $snap->word_count;
        }

        // Deduplicate and rank topics by frequency across all pages
        $topicFreq = array_count_values($allTopics);
        arsort($topicFreq);
        $topTopics = array_keys(array_slice($topicFreq, 0, 20, true));

        return [
            'scanned_pages'       => $pages,
            'page_types_found'    => array_values(array_unique($pageTypes)),
            'all_topics'          => $topTopics,
            'total_words_indexed' => $totalWords,
            'pages_scanned_count' => $snapshots->count(),
        ];
    }

    /**
     * Get just the brand voice for prompt injection.
     */
    public function getBrandVoice(Brand $brand): string
    {
        return $brand->brand_voice;
    }

    /**
     * Get the brand's configuration.
     */
    public function getConfig(Brand $brand): array
    {
        return $brand->config;
    }

    /**
     * Get the domain label.
     */
    public function getDomainLabel(string $domainType): string
    {
        return config("brand.domain_types.{$domainType}.label", ucfirst($domainType));
    }

    /**
     * Get the domain icon.
     */
    public function getDomainIcon(string $domainType): string
    {
        return config("brand.domain_types.{$domainType}.icon", '📋');
    }
}