<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Services\BrandManagementService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // Clear permission cache first
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create Vumbi Ventures brand
        $brand = Brand::create([
            'name' => 'Vumbi Ventures',
            'slug' => 'vumbiventures',

            'domain_type' => 'marketing',
            'config' => [
                'ga4_property_id' => env('VUMBI_GA4_PROPERTY_ID', '123456789'),
                'ga4_measurement_id' => env('VUMBI_GA4_MEASUREMENT_ID', 'G-XXXXXXXX'),
                'ga4_api_secret' => env('VUMBI_GA4_API_SECRET', 'secret'),

                'travel_payouts_api_key' => env('TRAVEL_PAYOUTS_API_KEY'),
                'bonusarrive_api_key' => env('BONUSARRIVE_API_KEY'),
                'awin_api_key' => env('AWIN_API_KEY'),
            ],
            'brand_voice' => 'Professional, adventurous, trustworthy, and data-driven. Speak with authority about African travel, technology, and business growth.',
            'timezone' => 'Africa/Nairobi',
            'is_active' => true,
            'settings' => [
                'default_language' => 'en',
                'currency' => 'KES',
                'country' => 'Kenya',
            ],
        ]);

        // Create default roles and permissions
        $brandService = app(BrandManagementService::class);
        $brandService->createDefaultRolesAndPermissions($brand);

        // Find or create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@vumbiventures.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
            ]
        );

        // Attach user to brand first (important!)
        $brand->users()->attach($admin->id);

        // Set the brand context for the user
        $admin->setBrandContext($brand);
        $admin->active_brand_id = $brand->id;
        $admin->save();

        // Get the owner role for this brand
        $role = Role::where('name', 'owner')
            ->where('brand_id', $brand->id)
            ->first();

        if ($role) {
            // Assign the role using the role model directly with brand context
            $admin->roles()->attach($role->id, ['brand_id' => $brand->id]);
        }

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('✅ Brand created: Vumbi Ventures');
        $this->command->info('📧 Email: admin@vumbiventures.com');
        $this->command->info('🔑 Password: password');
    }
}
