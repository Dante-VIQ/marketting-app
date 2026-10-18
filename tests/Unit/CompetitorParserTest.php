<?php

namespace Tests\Unit;

use App\Services\Competitors\CompetitorParser as P;
use PHPUnit\Framework\TestCase;

class CompetitorParserTest extends TestCase
{
    public function test_normalize_domain(): void
    {
        $this->assertSame('example.com', P::normalizeDomain('https://www.Example.com/blog?x=1'));
        $this->assertSame('safari.co.ke', P::normalizeDomain('safari.co.ke'));
        $this->assertNull(P::normalizeDomain('localhost'));
        $this->assertNull(P::normalizeDomain('not a domain'));
        $this->assertNull(P::normalizeDomain(''));
    }

    public function test_private_and_reserved_ips_are_rejected(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', '169.254.169.254', '172.16.0.1', '0.0.0.0', '::1'] as $ip) {
            $this->assertFalse(P::isPublicIp($ip), $ip);
        }
        $this->assertTrue(P::isPublicIp('93.184.216.34'));
    }

    public function test_sitemap_urlset_and_index(): void
    {
        $urlset = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '<url><loc>https://a.com/p1</loc><lastmod>2026-10-01</lastmod></url><url><loc>https://a.com/p2</loc></url></urlset>';
        $r = P::parseSitemap($urlset);
        $this->assertSame('urlset', $r['type']);
        $this->assertCount(2, $r['entries']);
        $this->assertSame('2026-10-01', $r['entries'][0]['lastmod']);
        $this->assertNull($r['entries'][1]['lastmod']);

        $index = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://a.com/post-sitemap.xml</loc></sitemap></sitemapindex>';
        $r = P::parseSitemap($index);
        $this->assertSame('index', $r['type']);
        $this->assertSame('https://a.com/post-sitemap.xml', $r['entries'][0]['loc']);

        $this->assertSame('invalid', P::parseSitemap('<html>nope')['type']);
        $this->assertSame('invalid', P::parseSitemap('')['type']);
    }

    public function test_feed_rss_and_atom(): void
    {
        $rss = '<rss><channel><item><title>Big Five &amp; more</title><link>https://a.com/1</link><pubDate>Mon, 12 Oct 2026 10:00:00 +0000</pubDate></item></channel></rss>';
        $f = P::parseFeed($rss);
        $this->assertSame('Big Five & more', $f[0]['title']);
        $this->assertSame('https://a.com/1', $f[0]['url']);
        $this->assertStringStartsWith('2026-10-12', $f[0]['date']);

        $atom = '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Maasai Mara</title><link href="https://a.com/2"/><updated>2026-10-10T08:00:00Z</updated></entry></feed>';
        $f = P::parseFeed($atom);
        $this->assertSame('https://a.com/2', $f[0]['url']);
        $this->assertStringStartsWith('2026-10-10', $f[0]['date']);

        $this->assertSame([], P::parseFeed('garbage'));
    }

    public function test_xxe_is_not_expanded(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss><channel><item><title>&x;</title></item></channel></rss>';
        $this->assertStringNotContainsString('root:', json_encode(P::parseFeed($xml)));
    }

    public function test_homepage(): void
    {
        $html = '<html><head><title> Acme  Safaris </title><meta name="Description" content="Tours in Kenya">'
            . '<link rel="alternate" type="application/rss+xml" href="/feed.xml"></head><body><h1>Go wild</h1></body></html>';
        $h = P::parseHomepage($html);
        $this->assertSame('Acme Safaris', $h['title']);
        $this->assertSame('Tours in Kenya', $h['description']);
        $this->assertSame('Go wild', $h['h1']);
        $this->assertSame('/feed.xml', $h['feed_url']);
        $this->assertSame(['title' => null, 'description' => null, 'h1' => null, 'feed_url' => null], P::parseHomepage(''));
    }

    public function test_absolute_url(): void
    {
        $this->assertSame('https://a.com/feed.xml', P::absoluteUrl('/feed.xml', 'https://a.com/'));
        $this->assertSame('https://b.com/x', P::absoluteUrl('https://b.com/x', 'https://a.com/'));
        $this->assertSame('https://cdn.com/x', P::absoluteUrl('//cdn.com/x', 'https://a.com/'));
    }

    public function test_top_terms_count_once_per_title_and_need_two_titles(): void
    {
        $t = P::topTerms([
            'Best Maasai Mara safari tips',
            'Maasai Mara migration guide',
            'Zanzibar beach holidays',
        ]);
        $this->assertArrayHasKey('maasai', $t);
        $this->assertArrayHasKey('maasai mara', $t);
        $this->assertSame(2, $t['maasai']);
        $this->assertArrayNotHasKey('zanzibar', $t);   // only one title
        $this->assertArrayNotHasKey('best', $t);       // stopword
    }

    public function test_topic_gaps_exclude_what_we_cover_and_rank_by_competitor_count(): void
    {
        $gaps = P::topicGaps(
            [['gorilla trekking' => 3, 'maasai' => 2], ['gorilla trekking' => 2, 'visa' => 2], ['visa' => 4]],
            ['Maasai Mara guide', 'Nairobi nightlife']
        );
        $terms = array_column($gaps, 'term');
        $this->assertNotContains('maasai', $terms);
        $this->assertSame('visa', $gaps[0]['term']);          // 2 competitors, 6 mentions beats gorilla's 2/5
        $this->assertSame(2, $gaps[0]['competitors']);
        $this->assertContains('gorilla trekking', $terms);
    }

    public function test_cadence_unknown_is_null_not_zero(): void
    {
        $now = strtotime('2026-10-18T00:00:00Z');
        $this->assertSame(['count' => null, 'latest' => null], P::cadence([null, null], $now));
        $c = P::cadence(['2026-10-15T00:00:00Z', '2026-10-01T00:00:00Z', '2026-08-01T00:00:00Z', null], $now);
        $this->assertSame(2, $c['count']);
        $this->assertStringStartsWith('2026-10-15', $c['latest']);
    }
}
