<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\ContentIdea;
use App\Services\Analytics\GA4Service;
use App\Services\ContentPlan\SocialSiteResults;
use Illuminate\Support\Carbon;
use Tests\Unit\Support\MetaTestCase;

class SocialSiteResultsTest extends MetaTestCase
{
    private function report(array $rows): array
    {
        return ['metricHeaders' => [['name' => 'keyEvents'], ['name' => 'sessions']],   // order differs on purpose: read by name
            'rows' => array_map(fn ($r) => ['dimensionValues' => [['value' => $r[0]]], 'metricValues' => [['value' => (string) $r[2]], ['value' => (string) $r[1]]]], $rows)];
    }

    private function posted(array $o = []): ContentIdea
    {
        return ContentIdea::create($o + ['brand_id' => 1, 'title' => 't', 'platform' => 'facebook', 'status' => 'posted', 'posted_at' => Carbon::now()->subDays(5),
            'link_url' => 'https://v.test/a?utm_source=facebook&utm_medium=social&utm_campaign=vumbiai-1']);
    }

    private function brand(): Brand
    {
        $b = new Brand();
        $b->id = 1;

        return $b;
    }

    public function test_parse_reads_metrics_by_name_and_ignores_other_campaigns(): void
    {
        $out = SocialSiteResults::parse($this->report([['vumbiai-12', 40, 3], ['summer-sale', 99, 9], ['(not set)', 5, 0], ['vumbiai-12', 10, 1]]));
        $this->assertSame([12 => ['sessions' => 50, 'key_events' => 4]], $out);
    }

    public function test_records_visits_per_post_and_zero_only_after_ga4_answered(): void
    {
        $a = $this->posted(['link_url' => 'https://v.test/a?utm_campaign=vumbiai-1']);
        $b = $this->posted(['link_url' => 'https://v.test/b?utm_campaign=vumbiai-2']);
        $ga4 = $this->createMock(GA4Service::class);
        $ga4->method('getSocialCampaignReport')->willReturn($this->report([['vumbiai-' . $a->id, 30, 2]]));

        $r = (new SocialSiteResults())->run($this->brand(), $ga4);

        $this->assertSame(2, $r['updated']);
        $this->assertSame([30, 2], [$a->fresh()->site_sessions, $a->fresh()->site_key_events]);
        $this->assertSame(0, $b->fresh()->site_sessions);   // GA4 answered, no visits from this post
        $this->assertNotNull($b->fresh()->site_measured_at);
    }

    public function test_failed_ga4_stores_nothing(): void
    {
        $a = $this->posted();
        $ga4 = $this->createMock(GA4Service::class);
        $ga4->method('getSocialCampaignReport')->willThrowException(new \Exception('boom'));

        $r = (new SocialSiteResults())->run($this->brand(), $ga4);

        $this->assertSame(0, $r['updated']);
        $this->assertStringContainsString('boom', $r['errors'][0]);
        $this->assertNull($a->fresh()->site_sessions);
        $this->assertNull($a->fresh()->site_measured_at);
    }

    public function test_untagged_hand_posts_and_fresh_posts_are_left_unknown(): void
    {
        $untagged = $this->posted(['link_url' => 'https://v.test/plain']);
        $noLink = $this->posted(['link_url' => null]);
        $fresh = $this->posted(['posted_at' => Carbon::now()->subHours(6)]);
        $ga4 = $this->createMock(GA4Service::class);
        $ga4->expects($this->never())->method('getSocialCampaignReport');

        $r = (new SocialSiteResults())->run($this->brand(), $ga4);

        $this->assertSame(0, $r['updated']);
        foreach ([$untagged, $noLink, $fresh] as $i) {
            $this->assertNull($i->fresh()->site_sessions);
        }
    }

    public function test_summary_states_are_honest(): void
    {
        $this->assertSame('no_tracked_posts', SocialSiteResults::summary(1)['state']);

        $a = $this->posted(['posted_at' => Carbon::now()->subHours(3)]);
        $this->assertSame('too_early', SocialSiteResults::summary(1)['state']);

        $a->update(['site_sessions' => 12, 'site_key_events' => 1, 'site_measured_at' => now()]);
        $s = SocialSiteResults::summary(1);
        $this->assertSame('ok', $s['state']);
        $this->assertSame([1, 12, 1], [$s['tracked_posts'], $s['site_sessions'], $s['key_events']]);
        $this->assertStringContainsString('not revenue', $s['note']);
    }
}
