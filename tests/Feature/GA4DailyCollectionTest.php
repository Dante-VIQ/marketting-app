<?php

namespace Tests\Feature;

use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Services\Analytics\AnalyticsCollectorService;
use App\Services\Analytics\GA4Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GA4DailyCollectionTest extends TestCase
{
    use RefreshDatabase;

    private function collector(array $data): AnalyticsCollectorService
    {
        $ga4 = Mockery::mock(GA4Service::class);
        $ga4->shouldReceive('isConfigured')->andReturn(true);
        $ga4->shouldReceive('getDailyData')->andReturn($data);

        return new AnalyticsCollectorService($ga4);
    }

    private function brand(): Brand
    {
        return Brand::create(['name' => 'B', 'slug' => 'b', 'is_active' => true, 'website_url' => 'https://example.com']);
    }

    public function test_stores_real_per_day_values_not_30_day_averages(): void
    {
        $brand = $this->brand();
        $this->collector([
            'daily' => [
                '2026-10-15' => ['activeUsers' => 100, 'sessions' => 150, 'keyEvents' => 5, 'totalRevenue' => 50, 'screenPageViews' => 400, 'engagementRate' => 0.61],
                '2026-10-16' => ['activeUsers' => 40, 'sessions' => 60, 'keyEvents' => 1, 'totalRevenue' => 0, 'screenPageViews' => 120, 'engagementRate' => 0.5],
            ],
            'pages' => [], 'channels' => [], 'errors' => [],
        ])->collectForBrand($brand);

        $v = fn ($d) => (float) AnalyticsSnapshot::where('brand_id', $brand->id)->where('metric', 'visitors')
            ->whereNull('dimension')->whereDate('date', $d)->value('value');

        $this->assertSame(100.0, $v('2026-10-15'));
        $this->assertSame(40.0, $v('2026-10-16'));   // days differ: a real series
        $this->assertSame(61.0, (float) AnalyticsSnapshot::where('metric', 'engagement_rate')->whereDate('date', '2026-10-15')->value('value'));
    }

    public function test_pages_and_channels_stored_and_stale_window_rows_replaced(): void
    {
        $brand = $this->brand();
        // Old behaviour: a 30-day total stored under one date for a page that is no longer top.
        AnalyticsSnapshot::create(['brand_id' => $brand->id, 'date' => '2026-10-16', 'source' => 'ga4',
            'metric' => 'visitors', 'dimension' => 'https://example.com/old', 'value' => 9999]);

        $this->collector([
            'daily' => ['2026-10-16' => ['activeUsers' => 40, 'sessions' => 60, 'keyEvents' => 1]],
            'pages' => ['2026-10-16' => ['/safari' => ['activeUsers' => 12, 'keyEvents' => 1]]],
            'channels' => ['2026-10-16' => ['Organic Search' => ['activeUsers' => 30, 'sessions' => 40, 'keyEvents' => 1]]],
            'errors' => [],
        ])->collectForBrand($brand);

        $this->assertDatabaseMissing('analytics_snapshots', ['dimension' => 'https://example.com/old']);
        $this->assertDatabaseHas('analytics_snapshots', ['metric' => 'visitors', 'dimension' => 'https://example.com/safari']);
        $this->assertDatabaseHas('analytics_snapshots', ['metric' => 'channel_sessions', 'dimension' => 'Organic Search']);
    }

    public function test_failed_pages_report_keeps_existing_page_rows(): void
    {
        $brand = $this->brand();
        AnalyticsSnapshot::create(['brand_id' => $brand->id, 'date' => '2026-10-16', 'source' => 'ga4',
            'metric' => 'visitors', 'dimension' => 'https://example.com/keep', 'value' => 5]);

        $this->collector([
            'daily' => ['2026-10-16' => ['activeUsers' => 40]],
            'pages' => [], 'channels' => [], 'errors' => ['pages' => 'quota'],
        ])->collectForBrand($brand);

        $this->assertDatabaseHas('analytics_snapshots', ['dimension' => 'https://example.com/keep']);
    }
}
