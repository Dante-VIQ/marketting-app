<?php

namespace Tests\Feature;

use App\Models\AiAction;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentLearningAndCountsTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.api_key' => 'k']);
        $this->brand = Brand::create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);
    }

    private function agent(): static
    {
        return $this->withHeader('X-API-Key', 'k');
    }

    public function test_learning_accepts_null_outcome_and_stores_null(): void
    {
        $this->agent()->postJson("/api/agent/learn/{$this->brand->id}", [
            'action_name' => 'run_site_scan',
            'was_successful' => null,
        ])->assertStatus(201);

        $this->assertDatabaseHas('agent_experiences', [
            'brand_id' => $this->brand->id,
            'was_successful' => null,
        ]);
    }

    public function test_action_count_filters_by_action_name(): void
    {
        foreach (['pause_campaign', 'adjust_campaign'] as $name) {
            AiAction::create([
                'brand_id' => $this->brand->id, 'title' => $name, 'category' => 'strategy',
                'action_name' => $name, 'priority' => 3, 'status' => 'pending', 'origin' => 'agent',
            ]);
        }

        $this->agent()->getJson("/api/agent/actions/count/{$this->brand->id}?action=pause_campaign")
            ->assertOk()->assertJsonPath('count', 1);

        $this->agent()->getJson("/api/agent/actions/count/{$this->brand->id}?category=strategy")
            ->assertOk()->assertJsonPath('count', 2);

        $this->agent()->getJson("/api/agent/actions/count/{$this->brand->id}")
            ->assertStatus(400);
    }

    public function test_cycle_runs_can_be_recorded_and_listed(): void
    {
        $this->agent()->postJson('/api/agent/cycles', [
            'brand_id' => $this->brand->id,
            'status' => 'completed',
            'summary' => ['processed' => 3],
        ])->assertStatus(201);

        $this->agent()->getJson("/api/agent/cycles/{$this->brand->id}")
            ->assertOk()->assertJsonPath('runs.0.summary.processed', 3);
    }
}
