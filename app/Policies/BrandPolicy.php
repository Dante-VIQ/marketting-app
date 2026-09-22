<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BrandPolicy
{
    /**
     * Everyone with an active brand membership can see the brand list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Brand $brand): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }
        return $user->belongsToBrand($brand->id);
    }

    /**
     * Who can create a new brand?
     * Super-admins always. Admins of any brand can also create new ones.
     * Note: this is deliberately loose — creating a brand is a genesis
     * operation, not a mutation of existing state.
     */
    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->hasRole('admin');
    }

    /**
     * Update a brand — owner, admin, or super-admin.
     *
     * The creator of a brand is assigned the 'owner' role by
     * BrandManagementService::createBrand. Without 'owner' here, a user
     * who creates a brand cannot edit it afterward.
     */
    public function update(User $user, Brand $brand): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($brand->id)) {
            return false;
        }

        return $this->hasBrandRole($user, $brand, ['owner', 'admin']);
    }

    public function delete(User $user, Brand $brand): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($brand->id)) {
            return false;
        }

        return $this->hasBrandRole($user, $brand, ['owner']);
    }

    /**
     * Only owners can toggle brand active/inactive.
     */
    public function toggleActive(User $user, Brand $brand): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($brand->id)) {
            return false;
        }

        return $this->hasBrandRole($user, $brand, ['owner']);
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function isSuperAdmin(User $user): bool
    {
        // super-admin is a global role (brand_id NULL), so no team-context
        // nuance — the default check is safe here.
        return $user->hasRole('super-admin');
    }

    /**
     * Check whether a user has one of the given roles *scoped to this brand*.
     *
     * We query the pivot directly rather than use $user->hasRole() because
     * Spatie's team-scoped check depends on getPermissionsTeamId() being
     * set correctly at the moment of the call. During policy evaluation that
     * is not guaranteed, and the check silently returns false.
     */
    protected function hasBrandRole(User $user, Brand $brand, array $allowedRoles): bool
    {
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.brand_id', $brand->id)
            ->pluck('roles.name')
            ->toArray();

        return !empty(array_intersect($roles, $allowedRoles));
    }
}