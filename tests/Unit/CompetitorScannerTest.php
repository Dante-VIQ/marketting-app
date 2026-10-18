<?php

namespace Tests\Unit;

use App\Services\Competitors\CompetitorScanner;
use Illuminate\Http\Client\Factory;
use PHPUnit\Framework\TestCase;

class CompetitorScannerTest extends TestCase
{
    private const NOW = 1792300000; // 2026-10-18

    private function scanner(Factory $http, array $map = []): CompetitorScanner
    {
        return new CompetitorScanner($http, fn ($host) => $map[$host] ?? ['93.184.216.34']);
    }

    private function site(Factory $http): void
    {
        $http->fake([
            'https://rival.com/' => $http->response('<html><head><title>Rival Safaris</title><meta name="description" content="Big five tours"><link rel="alternate" type="application/rss+xml" href="/blog/feed.xml"></head><body><h1>Safari like a pro</h1></body></html>'),
            'https://rival.com/blog/feed.xml' => $http->response('<rss><channel>'
                . '<item><title>Maasai Mara migration guide</title><link>https://rival.com/a</link><pubDate>Thu, 15 Oct 2026 10:00:00 +0000</pubDate></item>'
                . '<item><title>Maasai Mara budget tips</title><link>https://rival.com/b</link><pubDate>Mon, 05 Oct 2026 10:00:00 +0000</pubDate></item>'
                . '<item><title>Old post about visas</title><link>https://rival.com/c</link><pubDate>Mon, 01 Jun 2026 10:00:00 +0000</pubDate></item>'
                . '</channel></rss>'),
            'https://rival.com/robots.txt' => $http->response("User-agent: *\nSitemap: https://rival.com/sitemap.xml"),
            'https://rival.com/sitemap.xml' => $http->response('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://rival.com/blog/a</loc></url><url><loc>https://rival.com/tours</loc></url></urlset>'),
            '*' => $http->response('', 404),
        ]);
    }

    public function test_full_scan_extracts_homepage_feed_sitemap_and_cadence(): void
    {
        $http = new Factory();
        $this->site($http);
        $r = $this->scanner($http)->collect('rival.com', self::NOW);

        $this->assertSame('ok', $r['status']);
        $this->assertSame('Rival Safaris', $r['homepage_title']);
        $this->assertSame('Big five tours', $r['meta_description']);
        $this->assertSame('https://rival.com/blog/feed.xml', $r['feed_url']);
        $this->assertSame('feed', $r['cadence_basis']);
        $this->assertSame(2, $r['posts_last_30d']);
        $this->assertSame(2, $r['sitemap_url_count']);
        $this->assertArrayHasKey('maasai mara', $r['top_terms']);
        $this->assertSame('Maasai Mara migration guide', $r['recent_posts'][0]['title']);
    }

    public function test_unreachable_homepage_is_an_error_not_zeros(): void
    {
        $http = new Factory();
        $http->fake(['*' => $http->response('', 500)]);
        $r = $this->scanner($http)->collect('rival.com', self::NOW);
        $this->assertSame('error', $r['status']);
        $this->assertNull($r['posts_last_30d']);
        $this->assertNull($r['sitemap_url_count']);
    }

    public function test_hosts_resolving_to_private_addresses_are_never_requested(): void
    {
        $http = new Factory();
        $http->fake();
        $r = $this->scanner($http, ['evil.com' => ['93.184.216.34', '169.254.169.254']])->collect('evil.com', self::NOW);
        $this->assertSame('error', $r['status']);
        $http->assertNothingSent();
    }

    public function test_redirect_to_internal_address_is_blocked(): void
    {
        $http = new Factory();
        $http->fake([
            'https://rival.com/' => $http->response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            'http://rival.com/' => $http->response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            '*' => $http->response('secret', 200),
        ]);
        $r = $this->scanner($http)->collect('rival.com', self::NOW);
        $this->assertSame('error', $r['status']);
        $http->assertNotSent(fn ($req) => str_contains($req->url(), '169.254.169.254'));
    }

    public function test_sitemap_fallback_for_cadence_when_no_feed(): void
    {
        $http = new Factory();
        $http->fake([
            'https://rival.com/' => $http->response('<html><head><title>R</title></head></html>'),
            'https://rival.com/robots.txt' => $http->response('', 404),
            'https://rival.com/sitemap.xml' => $http->response('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                . '<url><loc>https://rival.com/blog/gorilla-trekking-guide</loc><lastmod>2026-10-14</lastmod></url>'
                . '<url><loc>https://rival.com/blog/gorilla-trekking-permits</loc><lastmod>2026-10-02</lastmod></url>'
                . '<url><loc>https://rival.com/blog/old</loc><lastmod>2025-01-01</lastmod></url></urlset>'),
            '*' => $http->response('', 404),
        ]);
        $r = $this->scanner($http)->collect('rival.com', self::NOW);
        $this->assertSame('sitemap', $r['cadence_basis']);
        $this->assertSame(2, $r['posts_last_30d']);
        $this->assertSame(3, $r['sitemap_url_count']);
        $this->assertSame('gorilla trekking guide', $r['recent_posts'][0]['title']);
        $this->assertNull($r['feed_url']);
    }

    public function test_title_from_url(): void
    {
        $this->assertSame('my first post', CompetitorScanner::titleFromUrl('https://a.com/blog/my-first-post/'));
    }
}
