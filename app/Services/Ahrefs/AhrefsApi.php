<?php

namespace App\Services\Ahrefs;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Thin, DB-free client for the Ahrefs API v3 (Site Explorer).
 *
 * Request shapes follow the Ahrefs API schema: `date` is required on the
 * domain-rating / metrics / backlinks-stats / organic-keywords endpoints, and
 * `select` columns must be valid identifiers (best_position, keyword_difficulty,
 * best_position_url, ...). Failures THROW — callers must never invent zero data.
 */
class AhrefsApi
{
    private Factory $http;
    private string $token;
    private string $endpoint;

    public function __construct(?Factory $http = null, ?string $token = null, ?string $endpoint = null)
    {
        $this->http     = $http ?? new Factory();
        $this->token    = self::resolveToken($token);
        $this->endpoint = rtrim((string) ($endpoint ?? self::configValue('services.ahrefs.endpoint', 'https://api.ahrefs.com/v3')), '/');
    }

    public static function configValue(string $key, mixed $default = null): mixed
    {
        if (!function_exists('app') || !app()->bound('config')) {
            return $default;
        }

        return config($key, $default);
    }

    public static function resolveToken(?string $configuredToken = null): string
    {
        $candidates = [
            $configuredToken,
            self::configValue('services.ahrefs.token'),
            function_exists('env') ? env('AHREFS_API_TOKEN') : null,
            function_exists('env') ? env('AHREFS_API_KEY') : null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $token = trim($candidate);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    /**
     * Domain-level numbers from three endpoints: domain-rating, metrics, backlinks-stats.
     */
    public function siteStats(string $domain, string $date, ?string $country = null): array
    {
        $rating  = $this->get('site-explorer/domain-rating', ['target' => $domain, 'date' => $date]);
        $metrics = $this->get('site-explorer/metrics', array_filter([
            'target' => $domain,
            'date' => $date,
            'country' => $country,
        ]));
        $links   = $this->get('site-explorer/backlinks-stats', ['target' => $domain, 'date' => $date]);

        return self::mapSiteStats($rating, $metrics, $links);
    }

    public static function mapSiteStats(array $rating, array $metrics, array $links): array
    {
        $dr = $rating['domain_rating'] ?? null;
        $m  = $metrics['metrics'] ?? null;
        $l  = $links['metrics'] ?? null;

        if (!is_array($dr) || !is_array($m) || !is_array($l)) {
            throw new RuntimeException('Ahrefs site stats response missing domain_rating/metrics sections');
        }

        return [
            'domain_rating'     => isset($dr['domain_rating']) ? (float) $dr['domain_rating'] : null,
            'ahrefs_rank'       => $dr['ahrefs_rank'] ?? null,
            'organic_traffic'   => $m['org_traffic'] ?? null,
            'organic_keywords'  => $m['org_keywords'] ?? null,
            'keywords_top3'     => $m['org_keywords_1_3'] ?? null,
            'traffic_value'     => isset($m['org_cost']) ? (int) round($m['org_cost'] / 100) : null, // USD cents -> USD
            'backlinks'         => $l['live'] ?? null,
            'referring_domains' => $l['live_refdomains'] ?? null,
            'backlinks_all_time'         => $l['all_time'] ?? null,
            'referring_domains_all_time' => $l['all_time_refdomains'] ?? null,
        ];
    }

    /**
     * Organic keywords with position change vs `$compareDate`.
     */
    public function keywords(string $domain, string $date, string $compareDate, int $limit = 100, ?string $country = null): array
    {
        $data = $this->get('site-explorer/organic-keywords', array_filter([
            'target'        => $domain,
            'date'          => $date,
            'date_compared' => $compareDate,
            'country'       => $country,
            'limit'         => $limit,
            'order_by'      => 'sum_traffic:desc',
            'select'        => 'keyword,best_position,best_position_prev,best_position_diff,best_position_url,'
                . 'volume,keyword_difficulty,sum_traffic,is_branded,is_commercial,is_transactional,is_informational',
        ]));

        return array_map([self::class, 'mapKeyword'], $data['keywords'] ?? []);
    }

    public static function mapKeyword(array $k): array
    {
        return [
            'keyword'           => $k['keyword'] ?? '',
            'position'          => $k['best_position'] ?? null,
            'previous_position' => $k['best_position_prev'] ?? null,
            // Computed here (not from best_position_diff) so the sign is unambiguous:
            // positive = moved UP the rankings (e.g. 12 -> 8 = +4).
            'position_change'   => (isset($k['best_position'], $k['best_position_prev']))
                ? (int) $k['best_position_prev'] - (int) $k['best_position']
                : null,
            'url'               => $k['best_position_url'] ?? null,
            'volume'            => $k['volume'] ?? null,
            'difficulty'        => $k['keyword_difficulty'] ?? null,
            'traffic'           => $k['sum_traffic'] ?? null,
            'is_branded'        => $k['is_branded'] ?? null,
            'intent'            => self::intent($k),
            'raw'               => $k,
        ];
    }

    private static function intent(array $k): ?string
    {
        foreach (['transactional', 'commercial', 'informational'] as $i) {
            if (!empty($k["is_{$i}"])) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Strongest backlinks, including ones lost since `$since` so losses are visible.
     */
    public function backlinks(string $domain, string $since, int $limit = 100): array
    {
        $data = $this->get('site-explorer/all-backlinks', [
            'target'      => $domain,
            'limit'       => $limit,
            'history'     => "since:{$since}",
            'aggregation' => '1_per_domain',
            'order_by'    => 'domain_rating_source:desc',
            'select'      => 'url_from,url_to,anchor,title,domain_rating_source,is_dofollow,is_nofollow,'
                . 'first_seen_link,last_visited,last_seen,is_lost,is_new,lost_reason,is_spam',
        ]);

        return array_map([self::class, 'mapBacklink'], $data['backlinks'] ?? []);
    }

    public static function mapBacklink(array $b): array
    {
        $lost = (bool) ($b['is_lost'] ?? false);

        return [
            'source_url'    => $b['url_from'] ?? '',
            'target_url'    => $b['url_to'] ?? '',
            'anchor'        => $b['anchor'] ?? null,
            'title'         => $b['title'] ?? null,
            'source_dr'     => $b['domain_rating_source'] ?? null,
            'is_nofollow'   => (bool) ($b['is_nofollow'] ?? false),
            'is_follow'     => (bool) ($b['is_dofollow'] ?? !($b['is_nofollow'] ?? false)),
            'first_seen'    => self::date($b['first_seen_link'] ?? null),
            // live links: last time Ahrefs verified them; lost links: when they were found gone
            'last_seen'     => self::date($lost ? ($b['last_seen'] ?? null) : ($b['last_visited'] ?? null)),
            'is_lost'       => $lost,
            'is_new'        => (bool) ($b['is_new'] ?? false),
            'lost_reason'   => $lost ? ($b['lost_reason'] ?? null) : null,
            'is_spam'       => (bool) ($b['is_spam'] ?? false),
            'raw'           => $b,
        ];
    }

    private static function date(?string $value): ?string
    {
        return $value ? substr($value, 0, 10) : null;
    }

    /**
     * Calls one endpoint and reports what happened WITHOUT throwing. Used by ahrefs:diagnose.
     *
     * @return array{path:string,status:?int,ok:bool,body:string}
     */
    public function probe(string $path, array $query): array
    {
        try {
            $r = $this->http->withToken($this->token)->timeout(30)->get("{$this->endpoint}/{$path}", $query);

            return ['path' => $path, 'status' => $r->status(), 'ok' => $r->successful(), 'body' => substr(trim($r->body()), 0, 300)];
        } catch (\Throwable $e) {
            return ['path' => $path, 'status' => null, 'ok' => false, 'body' => 'No response: ' . $e->getMessage()];
        }
    }

    public function tokenHint(): string
    {
        return $this->token === '' ? 'NOT SET' : 'set (' . strlen($this->token) . ' chars, ends …' . substr($this->token, -4) . ')';
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    private function get(string $path, array $query): array
    {
        $response = $this->http->withToken($this->token)
            ->timeout(60)
            // Retry only transient failures (network, 429, 5xx) — never 400/401/403.
            ->retry(2, 1500, function ($e) {
                return $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 504], true));
            }, throw: false)
            ->get("{$this->endpoint}/{$path}", $query);

        $this->assertOk($response, $path);

        return $response->json() ?? [];
    }

    private function assertOk(Response $response, string $path): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        if (in_array($status, [401, 403], true)) {
            // Ahrefs uses 401/403 for a bad token but also for plan limits and exhausted API units,
            // so show what Ahrefs actually said instead of blaming the token.
            throw new RuntimeException("Ahrefs API authorization failed (HTTP {$status}) on {$path}: " . substr(trim($response->body()), 0, 300)
                . ' (Causes: wrong token, token not for API v3, plan without API access, or API units used up. After editing .env run php artisan config:clear.)');
        }
        if ($status === 429) {
            throw new RuntimeException("Ahrefs API rate limit exceeded on {$path}.");
        }

        throw new RuntimeException("Ahrefs API error [{$status}] on {$path}: " . substr($response->body(), 0, 500));
    }
}
