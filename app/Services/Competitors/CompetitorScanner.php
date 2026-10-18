<?php

namespace App\Services\Competitors;

use App\Models\Competitor;
use App\Models\CompetitorSnapshot;
use Illuminate\Http\Client\Factory as Http;

/**
 * Free competitor scan: homepage, robots.txt, sitemap and blog feed, read directly.
 * No paid APIs and no keys. Every request is SSRF-guarded (public hosts only, redirects re-checked).
 */
class CompetitorScanner
{
    private const MAX_BODY = 2_000_000;
    private const MAX_HOPS = 3;
    private const MAX_SITEMAP_URLS = 5000;
    private const FEED_GUESSES = ['/feed', '/feed/', '/rss.xml', '/blog/feed', '/blog/rss.xml'];
    private const POST_HINT = '/post|blog|news|article|stories|journal/i';

    private Http $http;
    /** @var callable(string):string[] returns the IPs a host resolves to */
    private $resolver;

    public function __construct(?Http $http = null, ?callable $resolver = null)
    {
        $this->http = $http ?? app(Http::class);
        $this->resolver = $resolver ?? fn (string $host) => gethostbynamel($host) ?: [];
    }

    /** Scan and store today's snapshot (one per competitor per day). */
    public function scan(Competitor $competitor): CompetitorSnapshot
    {
        $attrs = $this->collect($competitor->domain, time());

        return CompetitorSnapshot::updateOrCreate(
            ['competitor_id' => $competitor->id, 'captured_on' => gmdate('Y-m-d')],
            $attrs + ['brand_id' => $competitor->brand_id]
        );
    }

    /**
     * Network only, no database. Returns attributes for a snapshot row.
     * Missing pieces are null (unknown), never zero.
     */
    public function collect(string $domain, int $nowTs): array
    {
        $base = "https://{$domain}";
        $out = [
            'status' => 'ok', 'error' => null, 'homepage_title' => null, 'meta_description' => null, 'h1' => null,
            'sitemap_url_count' => null, 'posts_last_30d' => null, 'cadence_basis' => null, 'latest_post_at' => null,
            'feed_url' => null, 'top_terms' => null, 'recent_posts' => null,
        ];

        $html = $this->get($base . '/') ?? $this->get("http://{$domain}/");
        if ($html === null) {
            return ['status' => 'error', 'error' => 'Homepage could not be fetched (down, blocked, or not a public site).'] + $out;
        }

        $home = CompetitorParser::parseHomepage($html);
        $out['homepage_title'] = $home['title'];
        $out['meta_description'] = $home['description'];
        $out['h1'] = $home['h1'];

        // Blog feed
        $candidates = [];
        if ($home['feed_url'] && ($abs = CompetitorParser::absoluteUrl($home['feed_url'], $base . '/'))) {
            $candidates[] = $abs;
        }
        foreach (self::FEED_GUESSES as $g) {
            $candidates[] = $base . $g;
        }
        $posts = [];
        foreach (array_slice(array_unique($candidates), 0, 4) as $url) {
            $items = CompetitorParser::parseFeed($this->get($url) ?? '');
            if ($items) {
                $posts = $items;
                $out['feed_url'] = $url;
                $out['cadence_basis'] = 'feed';
                break;
            }
        }

        // Sitemap (also the fallback source for cadence)
        [$count, $sitemapPosts] = $this->sitemap($base);
        $out['sitemap_url_count'] = $count;
        if (!$posts && $sitemapPosts) {
            $posts = $sitemapPosts;
            $out['cadence_basis'] = 'sitemap';
        }

        if ($posts) {
            $cad = CompetitorParser::cadence(array_column($posts, 'date'), $nowTs);
            $out['posts_last_30d'] = $cad['count'];
            $out['latest_post_at'] = $cad['latest'] ? gmdate('Y-m-d H:i:s', strtotime($cad['latest'])) : null;
            usort($posts, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
            $out['recent_posts'] = array_slice($posts, 0, 10);
            $terms = CompetitorParser::topTerms(array_column($posts, 'title'));
            $out['top_terms'] = $terms ?: null;
        }

        return $out;
    }

    /** @return array{0:?int,1:array} [url count, post-like entries as feed-shaped items] */
    private function sitemap(string $base): array
    {
        $robots = $this->get($base . '/robots.txt') ?? '';
        $start = null;
        if (preg_match('/^\s*sitemap:\s*(\S+)/im', $robots, $m)) {
            $start = $m[1];
        }
        $root = CompetitorParser::parseSitemap($this->get($start ?? $base . '/sitemap.xml') ?? '');
        if ($root['type'] === 'invalid') {
            return [null, []];
        }

        $sets = [];
        if ($root['type'] === 'urlset') {
            $sets[] = $root['entries'];
        } else {
            $locs = array_column($root['entries'], 'loc');
            usort($locs, fn ($a, $b) => (int) preg_match(self::POST_HINT, $b) <=> (int) preg_match(self::POST_HINT, $a));
            foreach (array_slice($locs, 0, 4) as $loc) {
                $child = CompetitorParser::parseSitemap($this->get($loc) ?? '');
                if ($child['type'] === 'urlset') {
                    $sets[] = $child['entries'];
                }
            }
        }

        $count = 0;
        $posts = [];
        foreach ($sets as $entries) {
            $count += count($entries);
            foreach ($entries as $e) {
                if (count($posts) < self::MAX_SITEMAP_URLS && $e['lastmod'] && preg_match(self::POST_HINT, $e['loc'])) {
                    $posts[] = ['title' => self::titleFromUrl($e['loc']), 'url' => $e['loc'], 'date' => $e['lastmod']];
                }
            }
        }

        return [min($count, self::MAX_SITEMAP_URLS * 4), $posts];
    }

    public static function titleFromUrl(string $url): string
    {
        $slug = basename(rtrim((string) parse_url($url, PHP_URL_PATH), '/'));

        return trim(preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', urldecode($slug))));
    }

    /** SSRF-guarded GET. Returns body (capped) or null on any failure / non-2xx / unsafe target. */
    private function get(string $url): ?string
    {
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            $ip = $this->safeIp($url);
            if ($ip === null) {
                return null;
            }

            $host = parse_url($url, PHP_URL_HOST);
            $port = parse_url($url, PHP_URL_SCHEME) === 'http' ? 80 : 443;
            $options = ['allow_redirects' => false];
            if (defined('CURLOPT_RESOLVE') && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // Pin the connection to the IP we just validated (blocks DNS rebinding).
                $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]];
            }

            try {
                $r = $this->http->withOptions($options)->timeout(10)
                    ->withHeaders(['User-Agent' => 'VumbiCompetitorScan/1.0 (+public pages only)'])->get($url);
            } catch (\Throwable $e) {
                return null;
            }

            if ($r->status() >= 300 && $r->status() < 400 && ($loc = $r->header('Location'))) {
                $next = CompetitorParser::absoluteUrl($loc, $url);
                if (!$next) {
                    return null;
                }
                $url = $next;
                continue;
            }

            return $r->successful() ? substr($r->body(), 0, self::MAX_BODY) : null;
        }

        return null;
    }

    /** First resolved IP if the URL is http(s) and EVERY resolved address is public; otherwise null. */
    private function safeIp(string $url): ?string
    {
        $p = parse_url($url);
        if (!$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        $host = trim($p['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);
        if (!$ips) {
            return null;
        }
        foreach ($ips as $ip) {
            if (!CompetitorParser::isPublicIp($ip)) {
                return null;
            }
        }

        return $ips[0];
    }
}
