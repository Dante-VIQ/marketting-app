<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\GuardianAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BrandManagementService
{
    protected BrandConfigValidator $validator;

    public function __construct(BrandConfigValidator $validator)
    {
        $this->validator = $validator;
    }

    // ============================================================
    // CREATE BRAND
    // ============================================================

    /**
     * Create a new brand with default roles and permissions.
     *
     * @throws AuthorizationException
     */
    public function createBrand(array $data, User $owner): Brand
    {
        // 🔒 Only super-admins and admins can create brands
        if (!Gate::forUser($owner)->allows('create', Brand::class)) {
            Log::warning('Unauthorized brand creation attempt', [
                'user_id' => $owner->id,
                'name'    => $data['name'] ?? 'unknown',
            ]);
            throw new AuthorizationException('You are not authorized to create brands.');
        }

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
            $data['timezone']    = $data['timezone']    ?? config('brand.defaults.timezone');
            $data['is_active']   = $data['is_active']   ?? config('brand.defaults.is_active');

            // Create brand
            $brand = Brand::create([
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'domain_type' => $domainType,
                'config'      => $config,
                'brand_voice' => $data['brand_voice'],
                'timezone'    => $data['timezone'],
                'is_active'   => $data['is_active'],
                'settings'    => $data['settings'] ?? null,
            ]);

            // Create default roles and permissions
            $this->createDefaultRolesAndPermissions($brand);

            // Attach user to brand
            $brand->users()->attach($owner->id);

            // Set brand context
            $owner->setBrandContext($brand);

            // Assign owner role for this brand
            $role = Role::where('name', 'owner')
                ->where('brand_id', $brand->id)
                ->first();

            if ($role) {
                $owner->roles()->attach($role->id, ['brand_id' => $brand->id]);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if (!$owner->active_brand_id) {
                $owner->switchBrand($brand);
            }

            // Audit log
            $this->logAction($brand->id, $owner, 'brand_created', [
                'brand_name'  => $brand->name,
                'domain_type' => $brand->domain_type,
            ]);

            Log::info('Brand created', [
                'brand_id'   => $brand->id,
                'brand_name' => $brand->name,
                'owner_id'   => $owner->id,
            ]);

            return $brand;
        });
    }

    // ============================================================
    // UPDATE BRAND
    // ============================================================

    /**
     * Update an existing brand.
     *
     * @throws AuthorizationException
     */
    public function updateBrand(Brand $brand, array $data, User $user): Brand
    {
        // 🔒 Only brand admins or super-admins can update
        if (!Gate::forUser($user)->allows('update', $brand)) {
            Log::warning('Unauthorized brand update attempt', [
                'brand_id' => $brand->id,
                'user_id'  => $user->id,
            ]);
            throw new AuthorizationException('You are not authorized to update this brand.');
        }

        return DB::transaction(function () use ($brand, $data, $user) {
            // Snapshot original values for diff logging
            $original = $brand->only(array_keys($data));

            if (isset($data['config'])) {
                $domainType = $data['domain_type'] ?? $brand->domain_type;
                $this->validator->validate($domainType, $data['config']);
            }

            if (isset($data['name']) && $data['name'] !== $brand->name) {
                $data['slug'] = Str::slug($data['name']);
            }

            $brand->update($data);

            $changes = array_diff_assoc($data, $original);

            $this->logAction($brand->id, $user, 'brand_updated', [
                'brand_name' => $brand->name,
                'changes'    => $changes,
            ]);

            Log::info('Brand updated', [
                'brand_id'   => $brand->id,
                'brand_name' => $brand->name,
                'updated_by' => $user->id,
            ]);

            return $brand;
        });
    }

    // ============================================================
    // DELETE BRAND
    // ============================================================

    /**
     * Delete a brand.
     *
     * @throws AuthorizationException
     */
    public function deleteBrand(Brand $brand, User $user): void
    {
        // 🔒 Only owner or super-admin can delete
        if (!Gate::forUser($user)->allows('delete', $brand)) {
            Log::warning('Unauthorized brand delete attempt', [
                'brand_id' => $brand->id,
                'user_id'  => $user->id,
            ]);
            throw new AuthorizationException('Only the brand owner can delete this brand.');
        }

        DB::transaction(function () use ($brand, $user) {
            $brandId   = $brand->id;
            $brandName = $brand->name;

            // Audit log BEFORE deleting
            $this->logAction($brandId, $user, 'brand_deleted', [
                'brand_name' => $brandName,
                'slug'       => $brand->slug,
            ]);

            // Remove user associations
            $brand->users()->detach();

            // Delete brand-specific roles and permissions
            $brand->roles()->delete();
            $brand->permissions()->delete();

            // Delete the brand
            $brand->delete();

            Log::info('Brand deleted', [
                'brand_id'   => $brandId,
                'brand_name' => $brandName,
                'deleted_by' => $user->id,
            ]);
        });
    }

    // ============================================================
    // TOGGLE ACTIVE
    // ============================================================

    /**
     * Toggle brand active status.
     *
     * @param User|null $user  If provided, authorization is enforced. If null, this is a system call.
     * @throws AuthorizationException
     */
    public function toggleActive(Brand $brand, ?User $user = null): bool
    {
        // 🔒 If a user is provided, check permission
        if ($user !== null) {
            if (!Gate::forUser($user)->allows('toggleActive', $brand)) {
                Log::warning('Unauthorized brand toggle attempt', [
                    'brand_id' => $brand->id,
                    'user_id'  => $user->id,
                ]);
                throw new AuthorizationException('You are not authorized to toggle this brand.');
            }
        }

        $brand->is_active = !$brand->is_active;
        $brand->save();

        $this->logAction($brand->id, $user, $brand->is_active ? 'brand_activated' : 'brand_deactivated', [
            'brand_name' => $brand->name,
            'is_active'  => $brand->is_active,
        ]);

        Log::info('Brand toggled', [
            'brand_id'  => $brand->id,
            'is_active' => $brand->is_active,
            'user_id'   => $user?->id,
        ]);

        return $brand->is_active;
    }

    // ============================================================
    // ASSIGN USER TO BRAND
    // ============================================================

    /**
     * Assign a user to a brand with a specific role.
     *
     * @param User $actingUser  The user performing the assignment (needs admin rights)
     * @throws AuthorizationException
     */
    public function assignUserToBrand(Brand $brand, User $targetUser, string $roleName, ?User $actingUser = null): void
    {
        // 🔒 Only brand admins or super-admins can assign users
        if ($actingUser !== null) {
            if (!Gate::forUser($actingUser)->allows('update', $brand)) {
                Log::warning('Unauthorized user assignment attempt', [
                    'brand_id'      => $brand->id,
                    'acting_user'   => $actingUser->id,
                    'target_user'   => $targetUser->id,
                ]);
                throw new AuthorizationException('You are not authorized to manage users for this brand.');
            }
        }

        $role = Role::where('name', $roleName)
            ->where('brand_id', $brand->id)
            ->first();

        if (!$role) {
            throw new \InvalidArgumentException("Role '{$roleName}' does not exist for this brand.");
        }

        DB::transaction(function () use ($brand, $targetUser, $role, $roleName, $actingUser) {
            // Attach user to brand if not already
            if (!$brand->users()->where('user_id', $targetUser->id)->exists()) {
                $brand->users()->attach($targetUser->id);
            }

            // Set the brand context
            $targetUser->setBrandContext($brand);

            // Avoid duplicate role assignment
            $alreadyHasRole = $targetUser->roles()
                ->where('role_id', $role->id)
                ->where('brand_id', $brand->id)
                ->exists();

            if (!$alreadyHasRole) {
                $targetUser->roles()->attach($role->id, ['brand_id' => $brand->id]);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->logAction($brand->id, $actingUser, 'user_assigned_to_brand', [
                'target_user_id' => $targetUser->id,
                'role'           => $roleName,
            ]);

            Log::info('User assigned to brand', [
                'brand_id'       => $brand->id,
                'target_user_id' => $targetUser->id,
                'role'           => $roleName,
                'acting_user_id' => $actingUser?->id,
            ]);
        });
    }

    // ============================================================
    // REMOVE USER FROM BRAND
    // ============================================================

    /**
     * Remove a user from a brand.
     *
     * @param User $actingUser  The user performing the removal
     * @throws AuthorizationException
     */
    public function removeUserFromBrand(Brand $brand, User $targetUser, ?User $actingUser = null): void
    {
        // 🔒 Only brand admins or super-admins can remove users
        if ($actingUser !== null) {
            if (!Gate::forUser($actingUser)->allows('update', $brand)) {
                Log::warning('Unauthorized user removal attempt', [
                    'brand_id'    => $brand->id,
                    'acting_user' => $actingUser->id,
                    'target_user' => $targetUser->id,
                ]);
                throw new AuthorizationException('You are not authorized to manage users for this brand.');
            }
        }

        // Prevent removing the last owner
        $ownerCount = $brand->users()->whereHas('roles', function ($q) use ($brand) {
            $q->where('name', 'owner')->where('brand_id', $brand->id);
        })->count();

        $isOwner = $targetUser->roles()
            ->where('name', 'owner')
            ->where('brand_id', $brand->id)
            ->exists();

        if ($ownerCount <= 1 && $isOwner) {
            throw new \Exception('Cannot remove the last owner of a brand.');
        }

        DB::transaction(function () use ($brand, $targetUser, $actingUser) {
            // Remove roles for this brand
            $targetUser->roles()->where('brand_id', $brand->id)->detach();

            // Detach from brand
            $brand->users()->detach($targetUser->id);

            // If this was the user's active brand, clear it
            if ($targetUser->active_brand_id === $brand->id) {
                $targetUser->active_brand_id = null;
                $targetUser->save();
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->logAction($brand->id, $actingUser, 'user_removed_from_brand', [
                'target_user_id' => $targetUser->id,
            ]);

            Log::info('User removed from brand', [
                'brand_id'       => $brand->id,
                'target_user_id' => $targetUser->id,
                'acting_user_id' => $actingUser?->id,
            ]);
        });
    }

    // ============================================================
    // DEFAULT ROLES & PERMISSIONS (internal, no auth needed)
    // ============================================================

    /**
     * Create default roles and permissions for a brand.
     * Internal helper – called only by createBrand (already authorized).
     */
    protected function createDefaultRolesAndPermissions(Brand $brand): void
    {
        $permissions = config('brand.permissions', []);
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'web',
                'brand_id'   => $brand->id,
            ]);
        }

        $roles = [
            'owner'  => ['manage-brand', 'manage-content', 'manage-campaigns', 'view-analytics', 'manage-users', 'manage-ai'],
            'admin'  => ['manage-content', 'manage-campaigns', 'view-analytics', 'manage-users'],
            'editor' => ['manage-content', 'view-analytics'],
            'viewer' => ['view-analytics'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => 'web',
                'brand_id'   => $brand->id,
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

    // ============================================================
    // AUDIT LOG
    // ============================================================

    /**
     * Write an audit log entry.
     */
    protected function logAction(int $brandId, ?User $user, string $eventType, array $metadata = []): void
    {
        try {
            GuardianAuditLog::create([
                'brand_id'    => $brandId,
                'user_id'     => $user?->id,
                'fingerprint' => 'brand_' . $eventType . '_' . $brandId . '_' . time(),
                'event_type'  => $eventType,
                'metadata'    => $metadata,
            ]);
        } catch (\Exception $e) {
            // Never break the caller if audit logging fails
            Log::warning('Failed to write brand audit log: ' . $e->getMessage());
        }
    }
}