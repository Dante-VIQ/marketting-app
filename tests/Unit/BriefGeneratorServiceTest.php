<?php

namespace Tests\Unit;

use App\Jobs\ProcessApprovedActionsJob;
use App\Jobs\RunAiVisibilityAuditJob;
use App\Models\Brand;
use App\Services\AI\AiGatewayService;
use App\Services\AI\BriefGeneratorService;
use App\Services\Analytics\DashboardDataService;
use App\Services\Bot\BotActionHandler;
use App\Services\Scanner\SiteProfileService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BriefGeneratorServiceTest extends TestCase
{
    public function test_ai_gateway_rejects_placeholder_api_keys(): void
    {
        putenv('AI_PROVIDER=gemini');
        putenv('GEMINI_API_KEY=your_gemini_api_key_here');

        $service = new AiGatewayService();

        $this->assertFalse($service->isAvailable());
    }

    public function test_bot_action_handler_queues_the_real_content_generation_job(): void
    {
        Bus::fake();

        $brand = new Brand([
            'id' => 99,
            'name' => 'Test Brand',
            'is_active' => true,
        ]);

        $handler = new BotActionHandler($this->createMock(DashboardDataService::class));
        $result = $handler->handle('queue_content_generation', ['confirm' => true], $brand);

        $this->assertSame('queue_content_generation', $result['action_type']);
        Bus::assertDispatched(ProcessApprovedActionsJob::class, function ($job) use ($brand) {
            $brandId = \Closure::bind(function () {
                return $this->brand->id;
            }, $job, $job)();

            return $brandId === $brand->id;
        });
    }

    public function test_bot_action_handler_requires_confirmation_before_queueing_actions(): void
    {
        Bus::fake();

        $brand = new Brand([
            'id' => 100,
            'name' => 'Audit Brand',
            'is_active' => true,
        ]);

        $handler = new BotActionHandler($this->createMock(DashboardDataService::class));
        $result = $handler->handle('queue_visibility_audit', [], $brand);

        $this->assertStringContainsString('confirm', strtolower($result['data']));
        Bus::assertNothingDispatched();
    }

    public function test_bot_action_handler_queues_visibility_audit_with_a_brand_object(): void
    {
        Bus::fake();

        $brand = new Brand([
            'id' => 101,
            'name' => 'Audit Brand',
            'is_active' => true,
        ]);

        $handler = new BotActionHandler($this->createMock(DashboardDataService::class));
        $result = $handler->handle('queue_visibility_audit', ['confirm' => true], $brand);

        $this->assertSame('queue_visibility_audit', $result['action_type']);
        Bus::assertDispatched(RunAiVisibilityAuditJob::class, function ($job) use ($brand) {
            return $job->brand->id === $brand->id;
        });
    }

    public function test_gemini_json_requests_use_json_response_mime_type(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"status":"ok","brief":"ready"}']], 'role' => 'model'],
                    'finishReason' => 'STOP',
                    'index' => 0,
                ]],
                'usageMetadata' => ['totalTokenCount' => 18],
            ], 200),
        ]);

        putenv('AI_PROVIDER=gemini');
        putenv('GEMINI_API_KEY=test-key');

        $service = new AiGatewayService();
        $result = $service->generate([
            'system_prompt' => 'Return valid JSON only.',
            'user_prompt' => 'Generate a brief response.',
            'response_format' => 'json',
            'max_tokens' => 256,
        ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return ($payload['generationConfig']['responseMimeType'] ?? null) === 'application/json';
        });

        $this->assertTrue($result['success']);
        $this->assertSame(['status' => 'ok', 'brief' => 'ready'], json_decode($result['content'], true));
    }

    public function test_parse_ai_response_removes_invalid_control_characters(): void
    {
        $service = new BriefGeneratorService(
            $this->createMock(AiGatewayService::class),
            $this->createMock(SiteProfileService::class)
        );

        $method = new \ReflectionMethod($service, 'parseAiResponse');
        $method->setAccessible(true);

        $response = "{\n\n  \"strategic_diagnosis\": \"Vumbi Ventures saw strong demand\x01 in key pages\",\n  \"estimated_revenue_impact\": 1500.00,\n  \"confidence_score\": 85,\n  \"actions\": [\n    {\n      \"title\": \"Optimize homepage conversion path\",\n      \"category\": \"web_copy\",\n      \"description\": \"Improve destination CTAs\",\n      \"suggested_content\": \"Try our trip planner\",\n      \"content_draft\": \"Sample copy\",\n      \"target_platform\": \"blog\",\n      \"target_url\": null,\n      \"estimated_impact\": 500.00,\n      \"priority\": 1\n    }\n  ]\n}";

        $parsed = $method->invoke($service, $response);

        $this->assertSame('Vumbi Ventures saw strong demand in key pages', $parsed['strategic_diagnosis']);
        $this->assertSame(1500.00, $parsed['estimated_revenue_impact']);
        $this->assertCount(1, $parsed['actions']);
    }

    public function test_parse_ai_response_recovers_from_truncated_json(): void
    {
        $service = new BriefGeneratorService(
            $this->createMock(AiGatewayService::class),
            $this->createMock(SiteProfileService::class)
        );

        $method = new \ReflectionMethod($service, 'parseAiResponse');
        $method->setAccessible(true);

        $response = '{
            "strategic_diagnosis": "Strong demand is coming from the homepage and guides.",
            "estimated_revenue_';

        $parsed = $method->invoke($service, $response);

        $this->assertSame('Strong demand is coming from the homepage and guides.', $parsed['strategic_diagnosis']);
    }
}
