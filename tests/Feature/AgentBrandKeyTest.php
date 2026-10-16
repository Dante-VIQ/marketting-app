<?php

namespace Tests\Feature;

use App\Models\AiAction;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentBrandKeyTest extends TestCase
{
    use RefreshDatabase;

    private Brand $a;
    private Brand $b;
    private string $keyA = 'vak_test_key_for_brand_a';

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.api_key' => 'shared', 'agent.allow_shared_key' => true]);
        $this->a = Brand::create(['name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $this->b = Brand::create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);
        $this->a->forceFill(['agent_api_key_hash' => hash('sha256', $this->keyA)])->save();
    }

    private function as(string $key): static
    {
        return $this->withHeader('X-API-Key', $key);
    }

    public function test_brand_key_works_for_its_own_brand(): void
    {
        $this->as($this->keyA)->getJson("/api/agent/brief/{$this->a->id}")->assertOk();
    }

    public function test_brand_key_is_forbidden_for_other_brand(): void
    {
        $this->as($this->keyA)->getJson("/api/agent/brief/{$this->b->id}")->assertStatus(403);
    }

    public function test_brand_key_cannot_use_other_brands_action_id(): void
    {
        $action = AiAction::create([
            'brand_id' => $this->b->id, 'title' => 't', 'category' => 'seo',
            'priority' => 3, 'status' => 'pending', 'origin' => 'agent',
        ]);
        $this->as($this->keyA)->postJson('/api/agent/verification/record', [
            'brand_id' => $this->a->id, 'action_id' => $action->id,
            'phase' => 'immediate', 'metrics_after' => [],
        ])->assertStatus(403);
    }

    public function test_brand_key_cannot_list_all_brands(): void
    {
        $this->as($this->keyA)->getJson('/api/agent/brands')->assertStatus(403);
    }

    public function test_unknown_key_is_401(): void
    {
        $this->as('nope')->getJson("/api/agent/brief/{$this->a->id}")->assertStatus(401);
    }

    public function test_shared_key_still_works_until_disabled(): void
    {
        $this->as('shared')->getJson('/api/agent/brands')->assertOk();

        config(['agent.allow_shared_key' => false]);
        $this->as('shared')->getJson('/api/agent/brands')->assertStatus(401);
    }
}
