<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class BrandManagementService
{
    protected BrandConfigValidator $validator;

    public function __construct(BrandConfigValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Create default roles and permissions for a brand.
     */
    public function createDefaultRolesAndPermissions(Brand $brand): void
    {
        $permissions = config('brand.permissions', []);
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
                'brand_id' => $brand->id,
            ]);
        }

        $roles = [
            'owner' => ['manage-brand', 'manage-content', 'manage-campaigns', 'view-analytics', 'manage-users', 'manage-ai'],
            'admin' => ['manage-content', 'manage-campaigns', 'view-analytics', 'manage-users'],
            'editor' => ['manage-content', 'view-analytics'],
            'viewer' => ['view-analytics'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'brand_id' => $brand->id,
            ]);

            foreach ($rolePermissions as $permName) {
                $permission = Permission::where('name', $permName)
                    ->where('brand_id', $brand->id)
                    ->first();
                if ($permission) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    /**
     * Create a new brand with default roles and permissions.
     */
    public function createBrand(array $data, User $owner): Brand
    {
        return DB::transaction(function () use ($data, $owner) {
            // Validate config
            $domainType = $data['domain_type'] ?? 'general';
            $config = $data['config'] ?? [];
            $this->validator->validate($domainType, $config);

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            // Set defaults
            $data['brand_voice'] = $data['brand_voice'] ?? config('brand.defaults.brand_voice');
            $data['timezone'] = $data['timezone'] ?? config('brand.defaults.timezone');
            $data['is_active'] = $data['is_active'] ?? config('brand.defaults.is_active');

            // Create brand
            $brand = Brand::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'domain_type' => $domainType,
                'config' => $config,
                'brand_voice' => $data['brand_voice'],
                'timezone' => $data['timezone'],
                'is_active' => $data['is_active'],
                'settings' => $data['settings'] ?? null,
            ]);

            // Create default roles and permissions for this brand
            $this->createDefaultRolesAndPermissions($brand);

            // Attach user to brand first
            $brand->users()->attach($owner->id);

            // Set the brand context for the user
            $owner->setBrandContext($brand);

            // Get the owner role for this brand
            $role = Role::where('name', 'owner')
                ->where('brand_id', $brand->id)
                ->first();

            if ($role) {
                // Assign the role with brand_id using the pivot table directly
                $owner->roles()->attach($role->id, ['brand_id' => $brand->id]);
            }

            // Clear permission cache
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // If user has no active brand, set this as active
            if (!$owner->active_brand_id) {
                $owner->switchBrand($brand);
            }

            Log::info('Brand created', [
                'brand_id' => $brand->id,
                'brand_name' => $brand->name,
                'owner_id' => $owner->id,
            ]);

            return $brand;
        });
    }

    /**
     * Update an existing brand.
     */
    public function updateBrand(Brand $brand, array $data, User $user): Brand
    {
        return DB::transaction(function () use ($brand, $data, $user) {
            if (isset($data['config'])) {
                $domainType = $data['domain_type'] ?? $brand->domain_type;
                $this->validator->validate($domainType, $data['config']);
            }

            if (isset($data['name']) && $data['name'] !== $brand->name) {
                $data['slug'] = Str::slug($data['name']);
            }

            $brand->update($data);

            Log::info('Brand updated', [
                'brand_id' => $brand->id,
                'brand_name' => $brand->name,
                'updated_by' => $user->id,
            ]);

            return $brand;
        });
    }

    /**
     * Delete a brand.
     */
    public function deleteBrand(Brand $brand, User $user): void
    {
        DB::transaction(function () use ($brand, $user) {
            // Remove user associations
            $brand->users()->detach();

            // Delete brand-specific roles and permissions
            $brand->roles()->delete();
            $brand->permissions()->delete();

            // Delete the brand
            $brand->delete();

            Log::info('Brand deleted', [
                'brand_id' => $brand->id,
                'brand_name' => $brand->name,
                'deleted_by' => $user->id,
            ]);
        });
    }

    /**
     * Toggle brand active status.
     */
    public function toggleActive(Brand $brand): bool
    {
        $brand->is_active = !$brand->is_active;
        $brand->save();

        Log::info('Brand toggled', [
            'brand_id' => $brand->id,
            'is_active' => $brand->is_active,
        ]);

        return $brand->is_active;
    }

    /**
     * Assign a user to a brand with a specific role.
     */
    public function assignUserToBrand(Brand $brand, User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)
            ->where('brand_id', $brand->id)
            ->first();

        if (!$role) {
            throw new \InvalidArgumentException("Role '{$roleName}' does not exist for this brand.");
        }

        // Attach user to brand if not already
        if (!$brand->users()->where('user_id', $user->id)->exists()) {
            $brand->users()->attach($user->id);
        }

        // Set the brand context
        $user->setBrandContext($brand);

        // Assign the role with brand_id
        $user->roles()->attach($role->id, ['brand_id' => $brand->id]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Remove a user from a brand.
     */
    public function removeUserFromBrand(Brand $brand, User $user): void
    {
        // Check if user is the last owner
        $ownerCount = $brand->users()->whereHas('roles', function ($q) use ($brand) {
            $q->where('name', 'owner')
                ->where('brand_id', $brand->id);
        })->count();

        $isOwner = $user->roles()->where('name', 'owner')
            ->where('brand_id', $brand->id)
            ->exists();

        if ($ownerCount <= 1 && $isOwner) {
            throw new \Exception('Cannot remove the last owner of a brand.');
        }

        // Remove roles for this brand
        $user->roles()->where('brand_id', $brand->id)->detach();

        // Detach from brand
        $brand->users()->detach($user->id);

        // If this was the user's active brand, clear it
        if ($user->active_brand_id === $brand->id) {
            $user->active_brand_id = null;
            $user->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::info('User removed from brand', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
        ]);
    }
}