<?php

namespace Tests\Unit;

use App\Services\Listening\ListeningCollector;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\TestCase;

class ListeningCollectorTest extends TestCase
{
    private function collector(Factory $http): ListeningCollector
    {
        return new ListeningCollector($http, fn () => null);
    }

    public function test_google_news_ok_and_term_is_sent_as_quoted_phrase(): void
    {
        $http = new Factory();
        $http->fake(['news.google.com/*' => $http->response('<rss><channel><item><title>Acme opens Nairobi office - Business Daily</title><link>https://n.com/1</link><pubDate>Thu, 15 Oct 2026 10:00:00 +0000</pubDate></item></channel></rss>')]);
        [$items, $status] = $this->collector($http)->fetch('google_news', 'Acme Safaris');

        $this->assertSame('ok', $status);
        $this->assertSame('Business Daily', $items[0]['publisher']);
        $http->assertSent(fn (Request $r) => str_contains(urldecode($r->url()), '"Acme Safaris" when:30d'));
    }

    public function test_reddit_blocked_is_reported_not_hidden(): void
    {
        $http = new Factory();
        $http->fake(['www.reddit.com/*' => $http->response('Blocked', 403)]);
        [$items, $status] = $this->collector($http)->fetch('reddit', 'Acme');
        $this->assertSame([], $items);
        $this->assertSame('blocked', $status);
    }

    public function test_server_error_and_exceptions_are_errors(): void
    {
        $http = new Factory();
        $http->fake(['hn.algolia.com/*' => $http->response('', 500)]);
        $this->assertSame('error', $this->collector($http)->fetch('hackernews', 'Acme')[1]);

        $http2 = new Factory();
        $http2->fake(['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);
        $this->assertSame('error', $this->collector($http2)->fetch('reddit', 'Acme')[1]);
    }

    public function test_no_results_is_ok_with_empty_list(): void
    {
        $http = new Factory();
        $http->fake(['hn.algolia.com/*' => $http->response(['hits' => []])]);
        [$items, $status] = $this->collector($http)->fetch('hackernews', 'NobodyKnowsUs');
        $this->assertSame([], $items);
        $this->assertSame('ok', $status);      // zero mentions is a valid answer
    }

    public function test_quotes_in_terms_cannot_break_the_query(): void
    {
        $http = new Factory();
        $http->fake(['hn.algolia.com/*' => $http->response(['hits' => []])]);
        $this->collector($http)->fetch('hackernews', 'Ac"me');
        $http->assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'query="Acme"'));
    }
}
