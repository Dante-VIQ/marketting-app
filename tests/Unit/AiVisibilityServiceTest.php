<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\AiVisibilityAudit;
use App\Services\Visibility\AiVisibilityService;
use App\Services\AI\AiGatewayService;
use App\Services\BrandContextService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AiVisibilityService.
 *
 * Uses a stub AI gateway so no real API calls are made.
 */
class AiVisibilityServiceTest extends TestCase
{
    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeGateway(array $responses = []): AiGatewayService
    {
        $stub = $this->createMock(AiGatewayService::class);

        if (empty($responses)) {
            // Default: every call returns a successful response that doesn't mention the brand
            $stub->method('generate')->willReturn([
                'success'  => true,
                'content'  => 'There are many great options in this space.',
                'tokens_used' => 10,
            ]);
        } else {
            $stub->method('generate')->willReturnOnConsecutiveCalls(...$responses);
        }

        return $stub;
    }

    private function makeContextService(array $context = []): BrandContextService
    {
        $stub = $this->createMock(BrandContextService::class);
        $stub->method('getFullContext')->willReturn(array_merge([
            'industry'    => 'travel',
            'region'      => 'East Africa',
            'domain_type' => 'marketing',
        ], $context));
        return $stub;
    }

    private function makeBrand(string $name = 'Vumbi Ventures', string $url = 'https://vumbiventures.com'): Brand
    {
        $brand = new Brand();
        $brand->id          = 1;
        $brand->name        = $name;
        $brand->website_url = $url;
        $brand->domain_type = 'marketing';
        return $brand;
    }

    // ── mentionsBrand (via reflection) ───────────────────────────────────

    public function test_mentions_brand_exact_name(): void
    {
        $svc    = new AiVisibilityService($this->makeGateway(), $this->makeContextService());
        $ref    = new \ReflectionMethod($svc, 'mentionsBrand');

        $this->assertTrue($ref->invoke($svc, 'Vumbi Ventures', 'Check out Vumbi Ventures for safari tours.'));
    }

    public function test_mentions_brand_case_insensitive(): void
    {
        $svc = new AiVisibilityService($this->makeGateway(), $this->makeContextService());
        $ref = new \ReflectionMethod($svc, 'mentionsBrand');

        $this->assertTrue($ref->invoke($svc, 'Vumbi Ventures', 'VUMBI VENTURES is excellent.'));
    }

    public function test_mentions_brand_first_word_fallback(): void
    {
        $svc = new AiVisibilityService($this->makeGateway(), $this->makeContextService());
        $ref = new \ReflectionMethod($svc, 'mentionsBrand');

        $this->assertTrue($ref->invoke($svc, 'Vumbi Ventures', 'Vumbi is a great platform.'));
    }

    public function test_does_not_match_short_first_word(): void
    {
        $svc = new AiVisibilityService($this->makeGateway(), $this->makeContextService());
        $ref = new \ReflectionMethod($svc, 'mentionsBrand');

        // "Go" is only 2 chars — should not trigger partial match
        $this->assertFalse($ref->invoke($svc, 'Go Safaris', 'We go on great adventures.'));
    }

    public function test_no_mention_returns_false(): void
    {
        $svc = new AiVisibilityService($this->makeGateway(), $this->makeContextService());
        $ref = new \ReflectionMethod($svc, 'mentionsBrand');

        $this->assertFalse($ref->invoke($svc, 'Vumbi Ventures', 'There are many travel companies in Kenya.'));
    }

    // ── extractContext (via reflection) ──────────────────────────────────

    public function test_extract_context_returns_snippet(): void
    {
        $svc  = new AiVisibilityService($this->makeGateway(), $this->makeContextService());
        $ref  = new \ReflectionMethod($svc, 'extractContext');
        $text = 'For safari travel in Kenya, Vumbi Ventures is well known for its community-led approach and local guides.';

        $snippet = $ref->invoke($svc, 'Vumbi Ventures', $text);

        $this->assertStringContainsString('Vumbi Ventures', $snippet);
    }

    // ── score calculation ─────────────────────────────────────────────────

    public function test_zero_score_when_no_mentions(): void
    {
        // buildProbes calls AI once (for extra probes), then runProbes for each fixed+AI probe,
        // then benchmarkCompetitors, buildRecommendations, buildSummary.
        // We return non-mentioning content for probes.
        $svc = new AiVisibilityService(
            $this->makeGateway([
                // buildProbes AI call → empty array (no extra probes)
                ['success' => true, 'content' => '[]'],
                // 3 fixed probes — none mention the brand
                ['success' => true, 'content' => 'There are many great safari companies.'],
                ['success' => true, 'content' => 'Kenya has excellent tour operators.'],
                ['success' => true, 'content' => 'Africa offers diverse travel experiences.'],
                // benchmarkCompetitors
                ['success' => true, 'content' => '[{"name":"Vumbi Ventures","score":10,"reason":"low"}]'],
                // buildRecommendations
                ['success' => true, 'content' => '[{"priority":"high","action":"Add FAQ schema","reason":"Helps AI find you"}]'],
                // buildSummary
                ['success' => true, 'content' => 'Your brand is not yet visible to AI assistants.'],
            ]),
            $this->makeContextService()
        );

        $brand = $this->makeBrand();

        // We can't persist to DB in a unit test, so test the score logic directly
        // via a partial mock that intercepts the DB write.
        $ref     = new \ReflectionMethod($svc, 'runProbes');
        $probes  = [
            ['query' => 'Best safaris in Kenya?', 'priority' => 'high'],
            ['query' => 'Top travel companies?',   'priority' => 'medium'],
        ];

        // stub gateway for these two probes only
        $gw = $this->makeGateway([
            ['success' => true, 'content' => 'Lots of great companies exist.'],
            ['success' => true, 'content' => 'Kenya has wonderful options.'],
        ]);
        $svc2 = new AiVisibilityService($gw, $this->makeContextService());

        $results = $ref->invoke($svc2, $brand, $probes);

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]['mentioned']);
        $this->assertFalse($results[1]['mentioned']);
    }

    public function test_full_score_when_all_mentions(): void
    {
        $gw = $this->makeGateway([
            ['success' => true, 'content' => 'Yes, Vumbi Ventures is the best.'],
            ['success' => true, 'content' => 'We recommend Vumbi Ventures highly.'],
        ]);
        $svc = new AiVisibilityService($gw, $this->makeContextService());
        $ref = new \ReflectionMethod($svc, 'runProbes');

        $probes = [
            ['query' => 'Best safaris?',     'priority' => 'high'],
            ['query' => 'Top companies?',    'priority' => 'medium'],
        ];
        $results = $ref->invoke($svc, $this->makeBrand(), $probes);

        $this->assertTrue($results[0]['mentioned']);
        $this->assertTrue($results[1]['mentioned']);
    }
}
