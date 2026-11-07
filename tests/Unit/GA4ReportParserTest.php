<?php

namespace Tests\Unit;

use App\Services\Analytics\AnalyticsCollectorService;
use App\Services\Analytics\GA4ReportParser;
use App\Services\Analytics\GA4Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GA4ReportParserTest extends TestCase
{
    private function row(array $dims, array $metrics): array
    {
        return [
            'dimensionValues' => array_map(fn($v) => ['value' => $v], $dims),
            'metricValues'    => array_map(fn($v) => ['value' => (string) $v], $metrics),
        ];
    }

    public function test_daily_reads_metrics_by_header_name_not_position(): void
    {
        $resp = [
            // deliberately NOT the order the collector requests
            'metricHeaders' => [['name' => 'sessions'], ['name' => 'activeUsers'], ['name' => 'keyEvents']],
            'rows' => [
                $this->row(['20261016'], [120, 80, 4]),
                $this->row(['20261015'], [100, 60, 0]),
            ],
        ];

        $daily = GA4ReportParser::daily($resp);

        $this->assertSame(['2026-10-15', '2026-10-16'], array_keys($daily));   // sorted by date
        $this->assertSame(80.0, $daily['2026-10-16']['activeUsers']);
        $this->assertSame(120.0, $daily['2026-10-16']['sessions']);
        $this->assertSame(4.0, $daily['2026-10-16']['keyEvents']);
    }

    public function test_pages_keep_top_n_per_day_by_active_users(): void
    {
        $rows = [];
        foreach (range(1, 40) as $i) {
            $rows[] = $this->row(['20261016', "/p{$i}"], [$i, 0]);
        }
        $resp = ['metricHeaders' => [['name' => 'activeUsers'], ['name' => 'keyEvents']], 'rows' => $rows];

        $pages = GA4ReportParser::pages($resp, 5)['2026-10-16'];

        $this->assertCount(5, $pages);
        $this->assertSame(['/p40', '/p39', '/p38', '/p37', '/p36'], array_keys($pages));
    }

    public function test_channels_grouped_by_day_and_name(): void
    {
        $resp = [
            'metricHeaders' => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'keyEvents']],
            'rows' => [
                $this->row(['20261016', 'Organic Search'], [50, 70, 3]),
                $this->row(['20261016', 'Direct'], [20, 25, 1]),
            ],
        ];

        $ch = GA4ReportParser::channels($resp);

        $this->assertSame(70.0, $ch['2026-10-16']['Organic Search']['sessions']);
        $this->assertSame(1.0, $ch['2026-10-16']['Direct']['keyEvents']);
    }

    public function test_empty_or_malformed_response_yields_empty_not_errors(): void
    {
        $this->assertSame([], GA4ReportParser::daily([]));
        $this->assertSame([], GA4ReportParser::daily(['metricHeaders' => [], 'rows' => [['dimensionValues' => [['value' => '']]]]]));
    }

    public function test_run_report_keeps_metric_headers_for_parser(): void
    {
        Http::fake([
            'https://analyticsdata.googleapis.com/v1beta/properties/123456:runReport' => Http::response([
                'metricHeaders' => [
                    ['name' => 'activeUsers'],
                    ['name' => 'sessions'],
                ],
                'rows' => [
                    [
                        'dimensionValues' => [['value' => '20261016']],
                        'metricValues' => [['value' => '80'], ['value' => '120']],
                    ],
                ],
                'rowCount' => 1,
            ], 200),
        ]);

        $service = new class extends GA4Service {
            public function exposeRunReport(string $propertyId, string $accessToken, array $payload, bool $mandatory): array
            {
                return $this->runReport($propertyId, $accessToken, $payload, $mandatory);
            }
        };

        $response = $service->exposeRunReport('123456', 'token', ['limit' => 1000], true);

        $this->assertSame(['activeUsers', 'sessions'], array_column($response['metricHeaders'], 'name'));
        $this->assertSame(80.0, GA4ReportParser::daily($response)['2026-10-16']['activeUsers']);
        $this->assertSame(120.0, GA4ReportParser::daily($response)['2026-10-16']['sessions']);
    }

    public function test_collection_window_backfills_first_time(): void
    {
        [$start, $end] = AnalyticsCollectorService::collectionWindow(null, Carbon::parse('2026-10-17'));
        $this->assertSame('2026-09-17', $start->toDateString());
        $this->assertSame('2026-10-16', $end->toDateString());   // yesterday; today is partial
    }

    public function test_collection_window_refreshes_recent_days(): void
    {
        [$start, $end] = AnalyticsCollectorService::collectionWindow(Carbon::parse('2026-10-15'), Carbon::parse('2026-10-17'));
        $this->assertSame('2026-10-12', $start->toDateString());   // last stored - 3
        $this->assertSame('2026-10-16', $end->toDateString());
    }

    public function test_collection_window_caps_long_gaps_at_backfill_limit(): void
    {
        [$start] = AnalyticsCollectorService::collectionWindow(Carbon::parse('2026-01-01'), Carbon::parse('2026-10-17'));
        $this->assertSame('2026-09-17', $start->toDateString());
    }

    public function test_collection_window_never_starts_after_end(): void
    {
        [$start, $end] = AnalyticsCollectorService::collectionWindow(Carbon::parse('2026-10-17'), Carbon::parse('2026-10-17'));
        $this->assertTrue($start->lte($end));
    }

    public function test_forced_backfill_ignores_stored_data_and_uses_given_days(): void
    {
        [$start, $end] = AnalyticsCollectorService::collectionWindow(Carbon::parse('2026-10-15'), Carbon::parse('2026-10-17'), 90);
        $this->assertSame('2026-07-19', $start->toDateString());
        $this->assertSame('2026-10-16', $end->toDateString());
    }
}
