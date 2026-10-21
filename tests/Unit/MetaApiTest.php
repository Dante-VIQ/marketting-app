<?php

namespace Tests\Unit;

use App\Services\Meta\MetaApiException;
use App\Services\Meta\MetaAssets;
use App\Services\Meta\MetaInsights;
use App\Services\Meta\MetaPublisher;
use Illuminate\Http\Client\Request;
use Tests\Unit\Support\MetaTestCase;

class MetaApiTest extends MetaTestCase
{
    private function fakeJson(array $map): void
    {
        $this->http->fake(array_map(fn ($b) => $b instanceof \Closure ? $b : $this->http->response($b[0], $b[1] ?? 200), $map));
    }

    public function test_token_goes_in_the_header_not_the_url(): void
    {
        $this->fakeJson(['*' => [['data' => []]]]);
        $this->graph()->get('me/accounts', ['fields' => 'id']);

        $this->http->assertSent(function (Request $r) {
            return $r->hasHeader('Authorization', 'Bearer user-token') && !str_contains($r->url(), 'user-token') && str_contains($r->url(), '/v25.0/me/accounts');
        });
    }

    public function test_api_error_throws_classified_exception(): void
    {
        $this->fakeJson(['*' => [['error' => ['code' => 190, 'message' => 'Session has expired']], 401]]);
        try {
            $this->graph()->get('me');
            $this->fail('expected exception');
        } catch (MetaApiException $e) {
            $this->assertSame('token_expired', $e->kind());
            $this->assertStringContainsString('expired', $e->hint());
        }
    }

    public function test_paginate_follows_cursors_and_stops(): void
    {
        $this->http->fake([
            '*' => $this->http->sequence()
                ->push(['data' => [['id' => 1]], 'paging' => ['next' => 'x', 'cursors' => ['after' => 'A']]])
                ->push(['data' => [['id' => 2]]]),
        ]);
        $rows = $this->graph()->paginate('me/accounts');
        $this->assertSame([['id' => 1], ['id' => 2]], $rows);
    }

    public function test_a_retired_metric_is_skipped_and_reported_not_zeroed(): void
    {
        $this->http->fake(function (Request $r) {
            $m = $r['metric'] ?? '';
            if (str_contains($m, ',')) {
                return $this->http->response(['error' => ['code' => 100, 'message' => 'invalid metric']], 400);
            }
            if ($m === 'post_total_media_view_unique') {
                return $this->http->response(['error' => ['code' => 100, 'message' => '(#100) metric no longer supported']], 400);
            }

            return $this->http->response(['data' => [['name' => $m, 'values' => [['value' => 11]]]]]);
        });

        [$data, $errors] = (new MetaInsights($this->graph()))->tolerant('123/insights', ['post_media_view', 'post_total_media_view_unique', 'post_clicks'], 'tok');

        $this->assertSame(['post_media_view', 'post_clicks'], array_column($data['data'], 'name'));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('post_total_media_view_unique', $errors[0]);
    }

    public function test_a_token_error_while_reading_metrics_is_not_swallowed(): void
    {
        $this->fakeJson(['*' => [['error' => ['code' => 190, 'message' => 'expired']], 401]]);
        $this->expectException(MetaApiException::class);
        (new MetaInsights($this->graph()))->tolerant('1/insights', ['post_media_view'], 'tok');
    }

    public function test_pages_listing_and_page_choice(): void
    {
        $this->fakeJson(['*' => [['data' => [
            ['id' => '1', 'name' => 'A', 'access_token' => 'pt1', 'instagram_business_account' => ['id' => '9', 'username' => 'a_ig']],
            ['id' => '2', 'name' => 'B', 'access_token' => 'pt2'],
        ]]]]);
        $assets = new MetaAssets($this->graph());
        $pages = $assets->pages();

        $this->assertSame('a_ig', $pages['1']['instagram_username']);
        $this->assertSame('B', $assets->pageFor(['meta_page_id' => '2'], $pages)['name']);
        $this->expectExceptionMessage('Several Pages');
        $assets->pageFor([], $pages);
    }

    public function test_facebook_text_post_and_photo_post(): void
    {
        $this->fakeJson(['*/feed' => [['id' => '1_100']], '*/photos' => [['id' => 'ph', 'post_id' => '1_200']]]);
        $pub = new MetaPublisher($this->graph());
        $page = ['id' => '1', 'name' => 'A', 'token' => 'pt'];

        $this->assertSame('1_100', $pub->facebook($page, 'hello', 'https://x.test')['id']);
        $this->assertSame('1_200', $pub->facebook($page, 'pic', null, 'https://x.test/a.jpg')['id']);
        $this->http->assertSent(fn (Request $r) => str_ends_with($r->url(), '/1/feed') && $r['message'] === 'hello' && $r->hasHeader('Authorization', 'Bearer pt'));
    }

    public function test_instagram_publish_flow_creates_container_then_publishes(): void
    {
        $this->http->fake([
            '*/9/media' => $this->http->response(['id' => 'c1']),
            '*/c1*' => $this->http->response(['status_code' => 'FINISHED']),
            '*/9/media_publish' => $this->http->response(['id' => 'm1']),
            '*/m1*' => $this->http->response(['permalink' => 'https://instagram.com/p/abc']),
        ]);
        $r = (new MetaPublisher($this->graph()))->instagram(['id' => '1', 'token' => 'pt', 'instagram_id' => '9'], 'cap', 'https://x.test/a.jpg');

        $this->assertSame('m1', $r['id']);
        $this->assertSame('https://instagram.com/p/abc', $r['url']);
    }

    public function test_instagram_errors_stop_before_publishing(): void
    {
        $this->http->fake(['*/9/media' => $this->http->response(['id' => 'c1']), '*/c1*' => $this->http->response(['status_code' => 'ERROR'])]);
        $this->expectExceptionMessage('could not process');
        (new MetaPublisher($this->graph()))->instagram(['id' => '1', 'token' => 'pt', 'instagram_id' => '9'], 'cap', 'https://x.test/a.jpg');
    }

    public function test_no_linked_instagram_is_a_clear_error(): void
    {
        $this->expectExceptionMessage('no linked Instagram');
        (new MetaPublisher($this->graph()))->instagram(['id' => '1', 'token' => 'pt'], 'cap', 'https://x.test/a.jpg');
    }

    private function flaky(int $failures, array $okBody, int &$calls): \Closure
    {
        return function () use ($failures, $okBody, &$calls) {
            $calls++;
            if ($calls <= $failures) {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Resolving timed out');
            }
            return $this->http->response($okBody);
        };
    }

    public function test_a_read_survives_a_brief_network_failure(): void
    {
        $calls = 0;
        $this->http->fake(['*' => $this->flaky(2, ['data' => [['id' => 1]]], $calls)]);
        $g = new \App\Services\Meta\GraphClient($this->http, 'user-token', 'v25.0', 2, 0);
        $this->assertSame(['data' => [['id' => 1]]], $g->get('me/accounts'));
        $this->assertSame(3, $calls);
    }

    public function test_a_read_gives_up_with_a_clear_message_when_the_network_stays_down(): void
    {
        $calls = 0;
        $this->http->fake(['*' => $this->flaky(99, [], $calls)]);
        $g = new \App\Services\Meta\GraphClient($this->http, 'user-token', 'v25.0', 2, 0);
        try {
            $g->get('me/accounts');
            $this->fail('expected exception');
        } catch (MetaApiException $e) {
            $this->assertStringContainsString('after 3 tries', $e->getMessage());
            $this->assertSame(3, $calls);
        }
    }

    public function test_a_write_is_never_repeated_because_it_could_post_twice(): void
    {
        $calls = 0;
        $this->http->fake(['*' => $this->flaky(99, [], $calls)]);
        $g = new \App\Services\Meta\GraphClient($this->http, 'user-token', 'v25.0', 2, 0);
        $this->expectException(MetaApiException::class);
        try {
            $g->post('77/feed', ['message' => 'hi']);
        } finally {
            $this->assertSame(1, $calls);
        }
    }
}
