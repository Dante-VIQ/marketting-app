<?php

namespace Tests\Unit;

use App\Services\AdPlatforms\AdActionExecutor;
use App\Services\AdPlatforms\AdPlatformException;
use App\Services\AdPlatforms\AdPlatformRegistry;
use App\Services\AdPlatforms\MetaAdsPlatform;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\TestCase;

class AdPlatformsTest extends TestCase
{
    private function meta(Factory $http, ?string $token = 'tok'): MetaAdsPlatform
    {
        return new MetaAdsPlatform(['access_token' => $token, 'api_version' => 'v21.0', 'currency_offset' => 100], $http);
    }

    public function test_pause_and_resume_post_status(): void
    {
        $http = new Factory();
        $http->fake(['graph.facebook.com/*' => $http->response(['success' => true], 200)]);
        $m = $this->meta($http);

        $m->pause('123');
        $m->resume('123');

        $http->assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_contains($r->url(), '/v21.0/123') && $r['status'] === 'PAUSED'
            && $r->hasHeader('Authorization', 'Bearer tok'));
        $http->assertSent(fn (Request $r) => ($r['status'] ?? null) === 'ACTIVE');
    }

    public function test_budget_is_sent_in_minor_units(): void
    {
        $http = new Factory();
        $http->fake(['graph.facebook.com/*' => $http->response(['success' => true], 200)]);
        $this->meta($http)->setDailyBudget('123', 12.5);
        $http->assertSent(fn (Request $r) => (int) $r['daily_budget'] === 1250);
    }

    public function test_fetch_normalizes_state(): void
    {
        $http = new Factory();
        $http->fake(['graph.facebook.com/*' => $http->response(['name' => 'Safari', 'status' => 'ACTIVE', 'daily_budget' => '2000'], 200)]);
        $s = $this->meta($http)->fetchCampaign('123');
        $this->assertSame('active', $s['status']);
        $this->assertSame(20.0, $s['daily_budget']);
        $this->assertSame('Safari', $s['name']);
    }

    public function test_platform_error_message_is_surfaced(): void
    {
        $http = new Factory();
        $http->fake(['graph.facebook.com/*' => $http->response(['error' => ['message' => 'Invalid OAuth token']], 400)]);
        $this->expectException(AdPlatformException::class);
        $this->expectExceptionMessage('Invalid OAuth token');
        $this->meta($http)->pause('123');
    }

    public function test_unconfigured_and_bad_ids_never_call_the_network(): void
    {
        $http = new Factory();
        $http->fake();
        $this->assertFalse($this->meta($http, null)->isConfigured());
        try {
            $this->meta($http, null)->pause('123');
            $this->fail('expected exception');
        } catch (AdPlatformException $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
        try {
            $this->meta($http)->pause('123/../me');
            $this->fail('expected exception');
        } catch (AdPlatformException $e) {
            $this->assertStringContainsString('Invalid Meta campaign id', $e->getMessage());
        }
        $http->assertNothingSent();
    }

    public function test_status_and_unit_helpers(): void
    {
        $this->assertSame('paused', MetaAdsPlatform::normalizeStatus('paused'));
        $this->assertSame('other', MetaAdsPlatform::normalizeStatus('ARCHIVED'));
        $this->assertSame(1999, MetaAdsPlatform::toMinorUnits(19.99));
        $this->assertSame(500, MetaAdsPlatform::toMinorUnits(500, 1));
    }

    public function test_budget_guard(): void
    {
        AdActionExecutor::assertBudgetChangeAllowed(100.0, 125.0, 0.25);   // exactly at limit
        AdActionExecutor::assertBudgetChangeAllowed(100.0, 80.0, 0.25);
        foreach ([[100.0, 126.0], [100.0, 0.0], [null, 50.0], [0.0, 50.0]] as [$cur, $new]) {
            try {
                AdActionExecutor::assertBudgetChangeAllowed($cur, $new, 0.25);
                $this->fail("should refuse {$cur}->{$new}");
            } catch (AdPlatformException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_registry_lookup(): void
    {
        $http = new Factory();
        $r = new AdPlatformRegistry(['meta' => $this->meta($http)]);
        $this->assertTrue($r->has('META'));
        $this->assertSame('meta', $r->get('Meta')->name());
        $this->assertFalse($r->has('tiktok'));
        $this->expectException(AdPlatformException::class);
        $r->get('tiktok');
    }
}
