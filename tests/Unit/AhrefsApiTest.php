<?php

namespace Tests\Unit;

use App\Services\Ahrefs\AhrefsApi;
use Illuminate\Http\Client\Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Framework-free: exercises request shapes and response mapping against the Ahrefs v3 schema. */
class AhrefsApiTest extends TestCase
{
    private const BASE = 'https://api.ahrefs.com/v3';

    private function api(Factory $http): AhrefsApi
    {
        return new AhrefsApi($http, 'tok', self::BASE);
    }

    public function test_site_stats_calls_three_endpoints_with_date_and_maps_real_metrics(): void
    {
        $http = new Factory();
        $http->fake([
            '*/site-explorer/domain-rating*'  => $http->response(['domain_rating' => ['domain_rating' => 41.6, 'ahrefs_rank' => 900123]]),
            '*/site-explorer/metrics*'        => $http->response(['metrics' => ['org_traffic' => 5400, 'org_keywords' => 812, 'org_keywords_1_3' => 37, 'org_cost' => 123456]]),
            '*/site-explorer/backlinks-stats*' => $http->response(['metrics' => ['live' => 2100, 'live_refdomains' => 340, 'all_time' => 3000, 'all_time_refdomains' => 400]]),
        ]);

        $stats = $this->api($http)->siteStats('example.com', '2026-10-17');

        $this->assertSame(41.6, $stats['domain_rating']);
        $this->assertSame(5400, $stats['organic_traffic']);
        $this->assertSame(812, $stats['organic_keywords']);
        $this->assertSame(37, $stats['keywords_top3']);
        $this->assertSame(1235, $stats['traffic_value']);   // cents -> USD
        $this->assertSame(2100, $stats['backlinks']);
        $this->assertSame(340, $stats['referring_domains']);

        $http->assertSentCount(3);
        $http->assertSent(fn($req) => str_contains($req->url(), 'domain-rating') && $req['date'] === '2026-10-17' && $req['target'] === 'example.com');
    }

    public function test_keywords_request_uses_required_date_and_valid_columns(): void
    {
        $http = new Factory();
        $http->fake(['*/organic-keywords*' => $http->response(['keywords' => [[
            'keyword' => 'safari kenya',
            'best_position' => 4,
            'best_position_prev' => 9,
            'best_position_url' => 'https://example.com/safari',
            'volume' => 1900,
            'keyword_difficulty' => 33,
            'sum_traffic' => 210,
            'is_branded' => false,
            'is_commercial' => true,
        ]]])]);

        $rows = $this->api($http)->keywords('example.com', '2026-10-17', '2026-09-17', 50, 'KE');

        $http->assertSent(function ($req) {
            return $req['date'] === '2026-10-17'
                && $req['date_compared'] === '2026-09-17'
                && $req['country'] === 'KE'
                && str_contains($req['select'], 'best_position')
                && str_contains($req['select'], 'keyword_difficulty')
                && !preg_match('/(^|,)(position|kd|best_url)(,|$)/', $req['select']);
        });

        $this->assertSame(4, $rows[0]['position']);
        $this->assertSame(9, $rows[0]['previous_position']);
        $this->assertSame(5, $rows[0]['position_change']);   // 9 -> 4 = moved up 5
        $this->assertSame('commercial', $rows[0]['intent']);
        $this->assertSame(33, $rows[0]['difficulty']);
    }

    public function test_keyword_without_previous_position_has_null_change(): void
    {
        $k = AhrefsApi::mapKeyword(['keyword' => 'new', 'best_position' => 12, 'best_position_prev' => null]);
        $this->assertNull($k['position_change']);
    }

    public function test_backlinks_include_dates_and_lost_status(): void
    {
        $http = new Factory();
        $http->fake(['*/all-backlinks*' => $http->response(['backlinks' => [
            [
                'url_from' => 'https://a.com/p',
                'url_to' => 'https://example.com/',
                'anchor' => 'safari',
                'title' => 'A',
                'domain_rating_source' => 72.0,
                'is_dofollow' => true,
                'is_nofollow' => false,
                'first_seen_link' => '2026-08-01T10:00:00Z',
                'last_visited' => '2026-10-10T00:00:00Z',
                'is_lost' => false,
                'is_new' => true,
                'is_spam' => false
            ],
            [
                'url_from' => 'https://b.com/p',
                'url_to' => 'https://example.com/x',
                'anchor' => 'x',
                'title' => 'B',
                'domain_rating_source' => 30.0,
                'is_dofollow' => false,
                'is_nofollow' => true,
                'first_seen_link' => '2026-01-01T00:00:00Z',
                'last_seen' => '2026-10-02T00:00:00Z',
                'is_lost' => true,
                'lost_reason' => 'notfound'
            ],
        ]])]);

        [$live, $lost] = $this->api($http)->backlinks('example.com', '2026-09-17', 100);

        $http->assertSent(fn($req) => $req['history'] === 'since:2026-09-17' && str_contains($req['select'], 'first_seen_link'));

        $this->assertSame('2026-08-01', $live['first_seen']);
        $this->assertSame('2026-10-10', $live['last_seen']);
        $this->assertFalse($live['is_lost']);
        $this->assertTrue($lost['is_lost']);
        $this->assertSame('2026-10-02', $lost['last_seen']);
        $this->assertSame('notfound', $lost['lost_reason']);
    }

    public function test_failure_throws_instead_of_returning_fake_zero_data(): void
    {
        $http = new Factory();
        $http->fake(['*' => $http->response(['error' => 'bad'], 400)]);

        $this->expectException(RuntimeException::class);
        $this->api($http)->siteStats('example.com', '2026-10-17');
    }

    public function test_auth_failure_has_clear_message(): void
    {
        $http = new Factory();
        $http->fake(['*' => $http->response([], 401)]);

        $this->expectExceptionMessage('authorization failed');
        $this->api($http)->keywords('example.com', '2026-10-17', '2026-09-17');
    }

    public function test_service_reads_ahrefs_api_key_alias_and_trims_whitespace(): void
    {
        putenv('AHREFS_API_TOKEN=');
        putenv('AHREFS_API_KEY=  alias-key-123  ');

        $this->assertSame('alias-key-123', AhrefsApi::resolveToken());
    }

    public function test_malformed_site_stats_response_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        AhrefsApi::mapSiteStats(['domain_rating' => ['domain_rating' => 10]], [], []);
    }

    public function test_transient_429_is_retried(): void
    {
        $http = new Factory();
        $http->fake(['*' => $http->sequence()
            ->push([], 429)
            ->push(['keywords' => []], 200)]);

        $this->assertSame([], $this->api($http)->keywords('example.com', '2026-10-17', '2026-09-17'));
        $http->assertSentCount(2);
    }
}
