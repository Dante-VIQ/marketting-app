<?php

namespace App\Services\Scanner;

use App\Models\PageSnapshot;

class SiteProfileService
{
    public function build(int $brandId, int $maxPages = 30): array
    {
        $snapshots = PageSnapshot::where('brand_id', $brandId)
            ->where('status', 'completed')
            ->orderByDesc('scraped_at')
            ->limit($maxPages)
            ->get();

        $pages = [];
        $allTopics = [];

        foreach ($snapshots as $snap) {
            $pages[] = [
                'url'             => $snap->url,
                'page_type'       => $snap->page_type,
                'title'           => $snap->title,
                'h1'              => data_get($snap->headings, 'h1'),
                'topics_covered'  => array_slice($snap->topics_covered ?? [], 0, 8),
                'content_excerpt' => mb_substr(strip_tags($snap->content ?? ''), 0, 300),
            ];

            foreach ($snap->topics_covered ?? [] as $topic) {
                $allTopics[] = strtolower($topic);
            }
        }

        $topicCounts = array_count_values($allTopics);
        arsort($topicCounts);
        $dominantTopics = array_slice(array_keys($topicCounts), 0, 20);

        return [
            'page_count'      => count($pages),
            'dominant_topics' => $dominantTopics,
            'pages'           => $pages,
        ];
    }
}