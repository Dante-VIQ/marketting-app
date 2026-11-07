<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Services\Meta\ContentPublisher;
use App\Services\Meta\MetaAssets;
use App\Services\Meta\MetaInsights;
use App\Services\Meta\MetaPublisher;
use App\Services\Meta\ResultsSync;
use DomainException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Tests\Unit\Support\MetaTestCase;

class MetaWorkflowTest extends MetaTestCase
{
    private function publisher(): ContentPublisher
    {
        $g = $this->graph();

        return new ContentPublisher(new MetaAssets($g), new MetaPublisher($g), fn () => []);
    }

    private function idea(array $o = []): ContentIdea
    {
        return ContentIdea::create($o + ['brand_id' => 1, 'title' => 't', 'platform' => 'facebook', 'copy' => 'Hello Kenya', 'status' => 'ready']);
    }

    private function fakePage(array $extra = []): void
    {
        $this->http->fake($extra + [
            '*/me/accounts*' => $this->http->response(['data' => [['id' => '1', 'name' => 'Vumbi', 'access_token' => 'pt', 'instagram_business_account' => ['id' => '9', 'username' => 'v']]]]),
            '*/1/feed' => $this->http->response(['id' => '1_55']),
        ]);
    }

    // ---- approval guards ----

    public function test_approval_refused_when_auto_posting_is_off(): void
    {
        $this->setConfig('meta.publishing.enabled', false);
        $this->expectExceptionMessage('switched off');
        $this->publisher()->approve($this->idea(), 7, Carbon::now()->addHour(), null, null);
    }

    public function test_approval_guards(): void
    {
        $p = $this->publisher();
        $cases = [
            ['platform' => 'tiktok', 'msg' => 'Facebook or Instagram'],
            ['copy' => '  ', 'msg' => 'post text'],
            ['platform' => 'instagram', 'msg' => 'public https image'],
            ['status' => 'posted', 'msg' => 'not yet posted'],
        ];
        foreach ($cases as $c) {
            $msg = array_pop($c);
            try {
                $p->approve($this->idea($c), 7, Carbon::now()->addHour(), null, null);
                $this->fail("should refuse: $msg");
            } catch (DomainException $e) {
                $this->assertStringContainsString($msg, $e->getMessage());
            }
        }
        $this->expectExceptionMessage('future');
        $p->approve($this->idea(), 7, Carbon::now()->subDay(), null, null);
    }

    public function test_private_and_http_media_urls_are_rejected(): void
    {
        $this->assertFalse(ContentPublisher::publicHttps('http://x.test/a.jpg'));
        $this->assertFalse(ContentPublisher::publicHttps('https://localhost/a.jpg'));
        $this->assertFalse(ContentPublisher::publicHttps('https://192.168.1.5/a.jpg'));
        $this->assertFalse(ContentPublisher::publicHttps('https://intranet/a.jpg'));
        $this->assertTrue(ContentPublisher::publicHttps('https://cdn.example.com/a.jpg'));
    }

    // ---- publishing ----

    public function test_approved_post_is_published_when_due(): void
    {
        $this->fakePage();
        $i = $this->idea();
        $p = $this->publisher();
        $p->approve($i, 7, Carbon::now()->addMinutes(1), null, 'https://vumbi.test/post');
        ContentIdea::whereKey($i->id)->update(['scheduled_at' => Carbon::now()->subMinute()]);

        $r = $p->publishDue();

        $i = $i->fresh();
        $this->assertSame(['posted' => 1, 'failed' => 0, 'skipped' => 0], $r);
        $this->assertSame('posted', $i->status);
        $this->assertSame('1_55', $i->remote_post_id);
        $this->assertSame(7, (int) $i->approved_by);
        $this->http->assertSent(fn (Request $q) => str_ends_with($q->url(), '/1/feed') && $q['message'] === 'Hello Kenya' && $q['link'] === 'https://vumbi.test/post?utm_source=facebook&utm_medium=social&utm_campaign=vumbiai-' . $i->id);
    }

    public function test_image_post_keeps_its_link_in_the_caption(): void
    {
        $this->fakePage(['*/1/photos*' => $this->http->response(['post_id' => '1_77'])]);
        $i = $this->idea();
        $p = $this->publisher();
        $p->approve($i, 7, Carbon::now()->addMinutes(1), 'https://cdn.example.com/a.jpg', 'https://vumbi.test/post');
        ContentIdea::whereKey($i->id)->update(['scheduled_at' => Carbon::now()->subMinute()]);

        $p->publishDue();

        $this->http->assertSent(fn (Request $q) => str_ends_with($q->url(), '/1/photos') && str_contains($q['caption'], "Hello Kenya\n\nhttps://vumbi.test/post?utm_source=facebook"));
    }

    public function test_nothing_is_posted_before_its_time_or_without_approval(): void
    {
        $this->fakePage();
        $future = $this->idea(['status' => 'scheduled', 'approved_by' => 7, 'scheduled_at' => Carbon::now()->addHour()]);
        $unapproved = $this->idea(['status' => 'scheduled', 'approved_by' => null, 'scheduled_at' => Carbon::now()->subHour()]);

        $r = $this->publisher()->publishDue();

        $this->assertSame(0, $r['posted']);
        $this->assertSame('scheduled', $future->fresh()->status);
        $this->assertSame('scheduled', $unapproved->fresh()->status);
        $this->http->assertNothingSent();
    }

    public function test_switch_off_publishes_nothing_even_if_approved_and_due(): void
    {
        $this->setConfig('meta.publishing.enabled', false);
        $i = $this->idea(['status' => 'scheduled', 'approved_by' => 7, 'scheduled_at' => Carbon::now()->subHour()]);

        $this->assertSame(0, $this->publisher()->publishDue()['posted']);
        $this->assertSame('scheduled', $i->fresh()->status);
    }

    public function test_failure_is_recorded_and_never_retried_automatically(): void
    {
        $this->fakePage(['*/1/feed' => $this->http->response(['error' => ['code' => 200, 'message' => 'Requires pages_manage_posts']], 403)]);
        $i = $this->idea(['status' => 'scheduled', 'approved_by' => 7, 'scheduled_at' => Carbon::now()->subMinute()]);
        $p = $this->publisher();

        $this->assertSame(1, $p->publishDue()['failed']);
        $i = $i->fresh();
        $this->assertSame('failed', $i->status);
        $this->assertStringContainsString('pages_manage_posts', $i->publish_error);

        $this->assertSame(0, $p->publishDue()['failed'] + $p->publishDue()['posted']);   // a second run does not touch it
    }

    public function test_daily_cap_defers_extra_posts(): void
    {
        $this->setConfig('meta.publishing.max_per_day', 1);
        $this->fakePage();
        $a = $this->idea(['status' => 'scheduled', 'approved_by' => 7, 'scheduled_at' => Carbon::now()->subMinutes(5), 'publish_mode' => 'auto']);
        $b = $this->idea(['status' => 'scheduled', 'approved_by' => 7, 'scheduled_at' => Carbon::now()->subMinutes(4), 'publish_mode' => 'auto']);

        $r = $this->publisher()->publishDue();

        $this->assertSame(1, $r['posted']);
        $this->assertSame(1, $r['skipped']);
        $this->assertSame('scheduled', $b->fresh()->status);   // waits, not lost
    }

    public function test_cancel_returns_it_to_manual(): void
    {
        $i = $this->idea(['status' => 'scheduled', 'approved_by' => 7, 'publish_mode' => 'auto', 'scheduled_at' => Carbon::now()->addDay()]);
        $this->publisher()->cancel($i);
        $i = $i->fresh();
        $this->assertSame('ready', $i->status);
        $this->assertNull($i->approved_by);
        $this->assertSame('manual', $i->publish_mode);
    }

    // ---- results sync ----

    private function sync(): ResultsSync
    {
        $g = $this->graph();

        return new ResultsSync($g, new MetaAssets($g), new MetaInsights($g), fn () => []);
    }

    public function test_import_creates_posted_ideas_once_and_refreshes_results(): void
    {
        $this->http->fake([
            '*/me/accounts*' => $this->http->response(['data' => [['id' => '1', 'name' => 'V', 'access_token' => 'pt']]]),
            '*/1/posts*' => $this->http->response(['data' => [['id' => '1_a', 'message' => 'Safari tips', 'created_time' => Carbon::now()->subDays(3)->toIso8601String(), 'permalink_url' => 'https://fb.test/a', 'status_type' => 'added_photos']]]),
            '*/1_a/insights*' => $this->http->response(['data' => [['name' => 'post_media_view', 'values' => [['value' => 800]]]]]),
            '*/1_a*' => $this->http->response(['reactions' => ['summary' => ['total_count' => 40]], 'comments' => ['summary' => ['total_count' => 5]]]),
        ]);

        $r1 = $this->sync()->run(1);
        $r2 = $this->sync()->run(1);

        $this->assertSame(1, $r1['imported']);
        $this->assertSame(0, $r2['imported']);                       // no duplicates
        $idea = ContentIdea::first();
        $this->assertSame('posted', $idea->status);
        $this->assertSame('image', $idea->format);
        $this->assertSame(800, (int) $idea->impressions);
        $this->assertSame(45, (int) $idea->engagements);
        $this->assertNull($idea->reach);                             // not returned: stays unknown
    }

    public function test_a_failing_network_does_not_stop_the_others(): void
    {
        $this->http->fake([
            '*/me/accounts*' => $this->http->response(['data' => [['id' => '1', 'name' => 'V', 'access_token' => 'pt', 'instagram_business_account' => ['id' => '9', 'username' => 'v']]]]),
            '*/1/posts*' => $this->http->response(['data' => [['id' => '1_a', 'message' => 'hi', 'created_time' => Carbon::now()->toIso8601String()]]]),
            '*/9/media*' => $this->http->response(['error' => ['code' => 200, 'message' => 'needs instagram_basic']], 403),
            '*' => $this->http->response(['data' => []]),
        ]);

        $r = $this->sync()->run(1);

        $this->assertSame(1, $r['imported']);
        $this->assertNotEmpty($r['errors']);
        $this->assertStringContainsString('Instagram', $r['errors'][0]);
    }

    public function test_expired_token_is_reported_not_thrown(): void
    {
        $this->http->fake(['*' => $this->http->response(['error' => ['code' => 190, 'message' => 'expired']], 401)]);
        $r = $this->sync()->run(1);
        $this->assertSame(0, $r['imported']);
        $this->assertStringContainsString('expired', $r['errors'][0]);
    }
}
