<?php

namespace Tests\Unit;

use App\Services\ContentPlan\ContentPerformance as P;
use App\Services\ContentPlan\ContentSuggester as S;
use PHPUnit\Framework\TestCase;

class ContentPerformanceTest extends TestCase
{
    private function post(string $format, int $imp, int $eng, string $day = '2026-10-12', string $platform = 'facebook'): array
    {
        return ['platform' => $platform, 'format' => $format, 'topic' => 't', 'posted_at' => "$day 09:00:00", 'impressions' => $imp, 'engagements' => $eng];
    }

    public function test_no_results_is_not_a_zero_rate(): void
    {
        $r = P::analyze([['platform' => 'x', 'format' => 'image', 'topic' => 't', 'posted_at' => null, 'impressions' => null, 'engagements' => null]]);
        $this->assertSame('no_results', $r['state']);
        $this->assertNull($r['overall_engagement_rate']);
    }

    public function test_few_posts_means_too_early_and_no_leader(): void
    {
        $r = P::analyze([$this->post('image', 100, 10), $this->post('text', 100, 1)]);
        $this->assertSame('too_early', $r['state']);
        $this->assertSame([], $r['leaders']);
        $this->assertSame(6, $r['needed']);
    }

    public function test_leader_needs_enough_posts_in_the_group(): void
    {
        $posts = [];
        foreach (range(1, 4) as $i) { $posts[] = $this->post('short_video', 1000, 100); }   // 10%
        foreach (range(1, 4) as $i) { $posts[] = $this->post('image', 1000, 20); }          // 2%
        $posts[] = $this->post('carousel', 100, 90);                                         // 90% but only 1 post

        $r = P::analyze($posts);

        $this->assertSame('ok', $r['state']);
        $this->assertSame('short_video', $r['leaders']['format']['label']);
    }

    public function test_rate_is_weighted_by_impressions(): void
    {
        $r = P::analyze(array_merge(
            array_fill(0, 4, $this->post('a', 1000, 100)),
            array_fill(0, 4, $this->post('b', 10, 5)),
        ));
        // 4 x 100/1000 = 10%; 4 x 5/10 = 50%. Sorted best first, and each rate is total engagements / total impressions.
        $this->assertSame(['b' => 50.0, 'a' => 10.0], array_column($r['groups']['format'], 'engagement_rate', 'label'));
    }

    public function test_suggestions_are_deduped_and_say_where_they_came_from(): void
    {
        $out = S::suggest(
            [['term' => 'visa', 'competitors' => 2, 'mentions' => 5]],
            [['title' => 'Best time for safari?', 'url' => 'https://x.test/1']],
            [['keyword' => 'kenya safari cost', 'position' => 9, 'volume' => 900]],
            [['name' => 'Planner', 'pains' => ['Hidden costs']]],
            ['Visa'],
        );
        $topics = array_column($out, 'topic');
        $this->assertNotContains('visa', $topics);          // already planned
        $this->assertContains('kenya safari cost', $topics);
        $this->assertSame('keyword', $out[0]['source']);    // closest to a win comes first
        $this->assertNotEmpty($out[0]['source_detail']);
    }

    public function test_no_signals_gives_no_suggestions(): void
    {
        $this->assertSame([], S::suggest([], [], [], []));
    }
}
