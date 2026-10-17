<?php

namespace Tests\Unit;

use App\Services\Analytics\AgentEvidenceService as E;
use PHPUnit\Framework\TestCase;

class AgentEvidenceServiceTest extends TestCase
{
    private function kw(string $k, ?int $pos, ?int $prev, int $vol = 100): array
    {
        return ['keyword' => $k, 'position' => $pos, 'previous_position' => $prev, 'volume' => $vol, 'url' => "/$k", 'traffic' => null];
    }

    public function test_channel_changes_sorted_with_null_when_no_baseline(): void
    {
        $r = E::channelChanges(['Organic' => 150.0, 'Direct' => 50.0, 'Social' => 10.0], ['Organic' => 100.0, 'Direct' => 100.0]);
        $this->assertSame('Organic', $r[0]['channel']);
        $this->assertSame(50.0, $r[0]['change_pct']);
        $this->assertSame(-50.0, $r[1]['change_pct']);
        $this->assertNull($r[2]['change_pct']);
    }

    public function test_stat_changes_unknown_is_null_not_zero(): void
    {
        $r = E::statChanges(['domain_rating' => 30, 'backlinks' => 500, 'organic_traffic' => null], ['domain_rating' => 28, 'backlinks' => null, 'organic_traffic' => 10]);
        $this->assertSame(2, $r['domain_rating']);
        $this->assertNull($r['backlinks']);
        $this->assertNull($r['organic_traffic']);
    }

    public function test_movers_split_up_and_down_and_skip_unknown(): void
    {
        $rows = [$this->kw('a', 3, 9), $this->kw('b', 15, 5), $this->kw('c', 8, null), $this->kw('d', 4, 4)];
        $m = E::movers($rows);
        $this->assertSame('a', $m['up'][0]['keyword']);
        $this->assertSame(6, $m['up'][0]['change']);
        $this->assertSame('b', $m['down'][0]['keyword']);
        $this->assertSame(-10, $m['down'][0]['change']);
        $this->assertCount(1, $m['up']);
        $this->assertCount(1, $m['down']);
    }

    public function test_striking_distance_filters_range_and_orders_by_volume(): void
    {
        $rows = [$this->kw('top', 2, 2, 900), $this->kw('near', 11, 12, 300), $this->kw('mid', 6, 6, 800),
                 $this->kw('far', 40, 40, 999), $this->kw('novol', 9, 9, 0)];
        $r = E::strikingDistance($rows);
        $this->assertSame(['mid', 'near'], array_column($r, 'keyword'));
    }

    public function test_ranking_drop_alert_shape_and_severity(): void
    {
        $a = E::rankingDropAlert($this->kw('safari', 14, 3) + ['change' => -11]);
        $this->assertSame('seo_issue', $a['type']);
        $this->assertSame('high', $a['severity']);
        $this->assertSame('ranking_drop', $a['payload']['issue_type']);
        $this->assertSame('/safari', $a['payload']['page']);
        $this->assertSame($a['id'], E::rankingDropAlert($this->kw('safari', 14, 3) + ['change' => -11])['id']);

        $b = E::rankingDropAlert($this->kw('x', 12, 7) + ['change' => -5]);
        $this->assertSame('medium', $b['severity']);
    }

    public function test_lost_backlink_alert(): void
    {
        $a = E::lostBacklinkAlert('news.example', 72.0, '/p', 'removed');
        $this->assertSame('high', $a['severity']);
        $this->assertSame('backlink_lost', $a['payload']['issue_type']);
        $this->assertSame('medium', E::lostBacklinkAlert('b.example', 45.0, null, null)['severity']);
    }
}
