<?php

namespace Tests\Feature;

use App\Models\AiAction;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentBrandAuthorizedTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-agent-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.api_key' => self::KEY]);
    }

    private function brand(bool $active): Brand
    {
        return Brand::create([
            'name' => 'B' . uniqid(),
            'slug' => 'b-' . uniqid(),
            'is_active' => $active,
        ]);
    }

    private function action(Brand $brand): AiAction
    {
        return AiAction::create([
            'brand_id' => $brand->id,
            'title' => 'Test',
            'category' => 'seo',
            'priority' => 3,
            'status' => 'pending',
            'origin' => 'agent',
        ]);
    }

    private function agent(): static
    {
        return $this->withHeader('X-API-Key', self::KEY);
    }

    public function test_missing_api_key_is_rejected(): void
    {
        $this->getJson('/api/agent/brands')->assertStatus(401);
    }

    public function test_inactive_brand_blocked_on_route_param(): void
    {
        $brand = $this->brand(false);
        $this->agent()->getJson("/api/agent/brief/{$brand->id}")->assertStatus(403);
    }

    public function test_inactive_brand_blocked_on_snake_case_body(): void
    {
        $brand = $this->brand(false);
        $this->agent()->postJson('/api/agent/calibration/record', [
            'brand_id' => $brand->id,
        ])->assertStatus(403);
    }

    public function test_unknown_brand_in_body_is_404(): void
    {
        $this->agent()->postJson('/api/agent/calibration/record', [
            'brand_id' => 999999,
        ])->assertStatus(404);
    }

    public function test_inactive_brand_blocked_via_action_id_route(): void
    {
        $action = $this->action($this->brand(false));
        $this->agent()->postJson("/api/agent/actions/{$action->id}/request-rollback", [
            'reason' => 'x',
        ])->assertStatus(403);
    }

    public function test_unknown_action_id_is_404(): void
    {
        $this->agent()->postJson('/api/agent/actions/999999/request-rollback', [
            'reason' => 'x',
        ])->assertStatus(404);
    }

    public function test_active_brand_passes_action_id_route(): void
    {
        $action = $this->action($this->brand(true));
        $this->agent()->postJson("/api/agent/actions/{$action->id}/request-rollback", [
            'reason' => 'x',
        ])->assertStatus(200);
    }
}
