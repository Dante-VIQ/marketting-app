<?php

namespace Tests\Unit;

use App\Services\AI\AiGatewayService;
use App\Services\AI\BriefGeneratorService;
use App\Services\Scanner\SiteProfileService;
use Tests\TestCase;

class BriefGeneratorServiceTest extends TestCase
{
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
