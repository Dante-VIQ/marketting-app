<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use App\Services\BrandManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BrandManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_brand_accepts_nested_config_arrays(): void
    {
        Gate::before(fn() => true);

        $brand = Brand::create([
            'name' => 'Old Brand',
            'slug' => 'old-brand-config-regression-' . uniqid(),
            'domain_type' => 'general',
            'config' => [
                'seo' => [
                    'title' => 'Old title',
                ],
            ],
            'brand_voice' => 'friendly',
            'timezone' => 'Africa/Nairobi',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test.user.' . uniqid() . '@example.com',
            'password' => bcrypt('secret-password'),
            'active_brand_id' => $brand->id,
        ]);

        app(BrandManagementService::class)->updateBrand($brand, [
            'name' => 'Renamed Brand',
            'domain_type' => 'general',
            'config' => [
                'seo' => [
                    'title' => 'New title',
                    'keywords' => ['ai', 'seo'],
                ],
            ],
        ], $user);

        $brand->refresh();

        $this->assertSame('renamed-brand', $brand->slug);
        $this->assertSame('New title', $brand->config['seo']['title']);
        $this->assertSame(['ai', 'seo'], $brand->config['seo']['keywords']);
    }
}
