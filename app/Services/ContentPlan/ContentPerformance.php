<?php

namespace App\Services\ContentPlan;

/**
 * Which kinds of posts work, judged honestly. Engagement rate = engagements / impressions.
 * A "leader" is only named when there is enough data; otherwise the answer is "too early".
 */
class ContentPerformance
{
    public const MIN_TOTAL = 8;   // measured posts before any conclusion
    public const MIN_GROUP = 3;   // posts in a group before it can lead

    /**
     * The best individual posts so far, with their real numbers. Descriptive only: it never says a format "works".
     * Posts with no measured impressions are left out (unknown is not zero).
     * @return array<int,array{title:?string,platform:?string,format:?string,impressions:int,engagement_rate_pct:float}>
     */
    public static function topPosts(array $posts, int $limit = 3): array
    {
        $rows = [];
        foreach ($posts as $p) {
            if (($p['impressions'] ?? 0) > 0 && isset($p['engagements'])) {
                $rows[] = ['title' => $p['title'] ?? ($p['topic'] ?? null), 'platform' => $p['platform'] ?? null, 'format' => $p['format'] ?? null,
                    'impressions' => (int) $p['impressions'], 'engagement_rate_pct' => round($p['engagements'] / $p['impressions'] * 100, 2)];
            }
        }
        usort($rows, fn ($a, $b) => $b['engagement_rate_pct'] <=> $a['engagement_rate_pct']);

        return array_slice($rows, 0, $limit);
    }

    /** @param array<int,array{platform:?string,format:?string,topic:?string,posted_at:?string,impressions:?int,engagements:?int}> $posts */
    public static function analyze(array $posts): array
    {
        $measured = array_values(array_filter($posts, fn ($p) => ($p['impressions'] ?? 0) > 0 && isset($p['engagements'])));
        $total = count($measured);
        $dims = ['platform' => fn ($p) => $p['platform'], 'format' => fn ($p) => $p['format'],
            'topic' => fn ($p) => $p['topic'], 'weekday' => fn ($p) => !empty($p['posted_at']) ? date('l', strtotime($p['posted_at'])) : null];

        $groups = [];
        $leaders = [];
        foreach ($dims as $dim => $key) {
            $acc = [];
            foreach ($measured as $p) {
                $k = $key($p);
                if ($k === null || $k === '') { continue; }
                $acc[$k]['n'] = ($acc[$k]['n'] ?? 0) + 1;
                $acc[$k]['imp'] = ($acc[$k]['imp'] ?? 0) + $p['impressions'];
                $acc[$k]['eng'] = ($acc[$k]['eng'] ?? 0) + $p['engagements'];
            }
            $rows = [];
            foreach ($acc as $label => $v) {
                $rows[] = ['label' => (string) $label, 'posts' => $v['n'], 'engagement_rate' => round($v['eng'] / $v['imp'] * 100, 2)];
            }
            usort($rows, fn ($a, $b) => $b['engagement_rate'] <=> $a['engagement_rate']);
            $groups[$dim] = $rows;

            $eligible = array_values(array_filter($rows, fn ($r) => $r['posts'] >= self::MIN_GROUP));
            if ($total >= self::MIN_TOTAL && count($eligible) >= 2) {
                $leaders[$dim] = $eligible[0];
            }
        }

        $overall = $total ? round(array_sum(array_column($measured, 'engagements')) / array_sum(array_column($measured, 'impressions')) * 100, 2) : null;

        return [
            'state' => $total === 0 ? 'no_results' : ($total < self::MIN_TOTAL ? 'too_early' : 'ok'),
            'measured_posts' => $total,
            'needed' => max(0, self::MIN_TOTAL - $total),
            'overall_engagement_rate' => $overall,
            'groups' => $groups,
            'leaders' => $leaders,
        ];
    }
}
