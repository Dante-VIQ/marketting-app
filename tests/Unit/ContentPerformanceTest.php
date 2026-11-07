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

    public function test_top_posts_ranks_by_rate_and_skips_unmeasured(): void
    {
        $posts = [
            ['title' => 'A', 'platform' => 'facebook', 'format' => 'image', 'impressions' => 200, 'engagements' => 10],
            ['title' => 'B', 'platform' => 'instagram', 'format' => 'short_video', 'impressions' => 100, 'engagements' => 20],
            ['title' => 'C', 'platform' => 'facebook', 'format' => 'text', 'impressions' => null, 'engagements' => null],
            ['title' => 'D', 'platform' => 'facebook', 'format' => 'text', 'impressions' => 0, 'engagements' => 0],
        ];
        $top = P::topPosts($posts, 3);
        $this->assertCount(2, $top);
        $this->assertSame('B', $top[0]['title']);
        $this->assertSame(20.0, $top[0]['engagement_rate_pct']);
        $this->assertSame('A', $top[1]['title']);
    }

    public function test_top_posts_empty_when_nothing_measured(): void
    {
        $this->assertSame([], P::topPosts([['title' => 'x', 'impressions' => null, 'engagements' => null]]));
    }

    public function test_real_comment_questions_become_ideas_first_and_are_not_repeated(): void
    {
        $out = S::suggest([], [], [], [], ['is it safe to self drive in the mara?'], 12, ['Is it safe to self drive in the Mara?', 'How much is a Kilimanjaro trek?', '  ']);
        $this->assertCount(1, $out);
        $this->assertSame('audience_comment', $out[0]['source']);
        $this->assertSame('How much is a Kilimanjaro trek?', $out[0]['topic']);
        $this->assertLessThanOrEqual(16, strlen($out[0]['source']));
    }
}
