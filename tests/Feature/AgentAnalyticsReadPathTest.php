<?php

namespace Tests\Feature;

use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Services\Analytics\AnalyticsSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAnalyticsReadPathTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.api_key' => 'k']);
        $this->brand = Brand::create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);
    }

    private function snap(string $date, string $metric, float $value, ?string $dimension = null): void
    {
        AnalyticsSnapshot::create([
            'brand_id' => $this->brand->id, 'date' => $date, 'source' => 'ga4',
            'metric' => $metric, 'dimension' => $dimension, 'value' => $value,
        ]);
    }

    /** 14 days: previous 7 days convert at $prevConv/day, last 7 at $curConv/day. */
    private function seed(float $prevConv, float $curConv, float $sessions = 50): void
    {
        for ($i = 13; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i)->toDateString();
            $this->snap($d, 'visitors', 40);
            $this->snap($d, 'sessions', $sessions);
            $this->snap($d, 'conversions', $i >= 7 ? $prevConv : $curConv);
        }
        $this->snap(Carbon::today()->toDateString(), 'visitors', 30, '/safari');
    }

    private function agent(): static
    {
        return $this->withHeader('X-API-Key', 'k');
    }

    public function test_no_data_means_no_alert_and_no_fake_zero_alert(): void
    {
        $svc = app(AnalyticsSummaryService::class);
        $summary = $svc->summary($this->brand->id);

        $this->assertFalse($summary['has_data']);
        $this->assertNull($svc->conversionAlert($summary));

        $res = $this->agent()->getJson("/api/agent/opportunities/{$this->brand->id}")->assertOk();
        $this->assertNotContains('analytics_alert', collect($res->json('opportunities'))->pluck('type')->all());
    }

    public function test_agent_analytics_returns_real_window_totals(): void
    {
        $this->seed(prevConv: 5, curConv: 5);

        $this->agent()->getJson("/api/agent/analytics/{$this->brand->id}")
            ->assertOk()
            ->assertJsonPath('hasData', true)
            ->assertJsonPath('visitors', 280)      // 7 days x 40
            ->assertJsonPath('conversions', 35)    // 7 days x 5
            ->assertJsonPath('trend', 'stable')
            ->assertJsonPath('topPages.0.path', '/safari');
    }

    public function test_conversion_drop_raises_alert_with_numbers(): void
    {
        $this->seed(prevConv: 10, curConv: 4); // -60%

        $svc = app(AnalyticsSummaryService::class);
        $alert = $svc->conversionAlert($svc->summary($this->brand->id));

        $this->assertSame('conversion_drop', $alert['reason']);

        $res = $this->agent()->getJson("/api/agent/opportunities/{$this->brand->id}")->assertOk();
        $types = collect($res->json('opportunities'))->pluck('type')->all();
        $this->assertContains('analytics_alert', $types);
    }

    public function test_steady_conversions_raise_no_alert_even_when_low_in_absolute_terms(): void
    {
        $this->seed(prevConv: 1, curConv: 1);
        $svc = app(AnalyticsSummaryService::class);
        $this->assertNull($svc->conversionAlert($svc->summary($this->brand->id)));
    }

    public function test_traffic_without_conversions_raises_alert(): void
    {
        $this->seed(prevConv: 0, curConv: 0, sessions: 50); // 350 sessions / 7d, 0 conversions
        $svc = app(AnalyticsSummaryService::class);
        $this->assertSame('no_conversions', $svc->conversionAlert($svc->summary($this->brand->id))['reason']);
    }

    public function test_analyze_analytics_404_without_data_and_uses_real_numbers_with_data(): void
    {
        $this->agent()->getJson("/api/agent/analytics/analyze/{$this->brand->id}")->assertStatus(404);

        $this->seed(prevConv: 10, curConv: 4);
        $this->agent()->getJson("/api/agent/analytics/analyze/{$this->brand->id}")
            ->assertOk()
            ->assertJsonPath('analytics.trend', 'down');
    }
}
