<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\PageSnapshot;

class BrandContextService
{
    /**
     * Get the full brand context for AI prompts.
     *
     * Enriches base brand fields with data from completed page scanner snapshots
     * so AI services have a richer, more accurate picture of what the brand does.
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

        return array_merge($base, $pageContext);
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