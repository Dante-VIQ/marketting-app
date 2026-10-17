<?php

namespace Tests\Feature;

use App\Models\AhrefsKeyword;
use App\Models\Brand;
use App\Models\KeywordRanking;
use App\Services\Seo\KeywordRankingSync;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordRankingSyncTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): Brand
    {
        return Brand::create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);
    }

    public function test_syncs_real_ahrefs_rows_with_previous_position(): void
    {
        $brand = $this->brand();
        AhrefsKeyword::create([
            'brand_id' => $brand->id, 'keyword' => 'safari kenya', 'position' => 4, 'previous_position' => 9,
            'search_volume' => 1900, 'difficulty' => '33', 'tracked_date' => Carbon::today(),
        ]);

        $this->assertSame(1, app(KeywordRankingSync::class)->sync($brand));

        $row = KeywordRanking::where('brand_id', $brand->id)->first();
        $this->assertSame(4, $row->position);
        $this->assertSame(9, $row->previous_position);
        $this->assertSame('medium', $row->difficulty);
        $this->assertSame('ahrefs', $row->metadata['source']);
    }

    public function test_without_fresh_ahrefs_data_nothing_is_invented(): void
    {
        $brand = $this->brand();
        AhrefsKeyword::create([
            'brand_id' => $brand->id, 'keyword' => 'old', 'position' => 3,
            'tracked_date' => Carbon::today()->subDays(10),
        ]);

        $this->assertSame(0, app(KeywordRankingSync::class)->sync($brand));
        $this->assertSame(0, KeywordRanking::where('brand_id', $brand->id)->count());
    }

    public function test_agent_rankings_endpoint_returns_latest_snapshot_only(): void
    {
        config(['agent.api_key' => 'k']);
        $brand = $this->brand();
        foreach ([Carbon::today()->subDay(), Carbon::today()] as $day) {
            KeywordRanking::create([
                'brand_id' => $brand->id, 'keyword' => 'safari', 'position' => 5,
                'previous_position' => 8, 'tracked_date' => $day,
            ]);
        }

        $this->withHeader('X-API-Key', 'k')
            ->getJson("/api/agent/seo/rankings/{$brand->id}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.change', 3);
    }
}
