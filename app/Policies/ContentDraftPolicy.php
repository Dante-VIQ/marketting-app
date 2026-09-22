<?php

namespace App\Policies;

use App\Models\ContentDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContentDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasAnyRoleAcrossBrands(
            $user,
            ['super-admin', 'owner', 'admin', 'editor', 'viewer']
        );
    }

    public function view(User $user, ContentDraft $draft): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }
        return $user->belongsToBrand($draft->brand_id);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRoleAcrossBrands(
            $user,
            ['super-admin', 'owner', 'admin', 'editor']
        );
    }

    /**
     * Update a draft — owner, admin, or editor of the brand.
     *
     * Note: ContentDraft has no `created_by` column, so the old
     * "original author" branch was dead code. Removed.
     */
    public function update(User $user, ContentDraft $draft): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($draft->brand_id)) {
            return false;
        }

        return $this->hasBrandRole($user, $draft->brand_id, ['owner', 'admin', 'editor']);
    }

    public function delete(User $user, ContentDraft $draft): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($draft->brand_id)) {
            return false;
        }

        return $this->hasBrandRole($user, $draft->brand_id, ['owner', 'admin']);
    }

    public function publish(User $user, ContentDraft $draft): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($draft->brand_id)) {
            return false;
        }

        return $this->hasBrandRole($user, $draft->brand_id, ['owner', 'admin']);
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function isSuperAdmin(User $user): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->whereNull('roles.brand_id')
            ->where('roles.name', 'super-admin')
            ->exists();
    }

    /**
     * Direct DB query on model_has_roles. Avoids Spatie's team-context
     * trap where hasRole() silently returns false if team is unset.
     */
    protected function hasBrandRole(User $user, int $brandId, array $allowedRoles): bool
    {
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.brand_id', $brandId)
            ->where('roles.brand_id', $brandId)
            ->pluck('roles.name')
            ->toArray();

        return !empty(array_intersect($roles, $allowedRoles));
    }



    /**
     * Check for any of the given roles across all brands the user
     * belongs to, plus any global (brand_id NULL) roles.
     */
    protected function hasAnyRoleAcrossBrands(User $user, array $allowedRoles): bool
    {
        // Global roles (super-admin etc.)
        $globalRoles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->whereNull('roles.brand_id')
            ->pluck('roles.name')
            ->toArray();

        if (!empty(array_intersect($globalRoles, $allowedRoles))) {
            return true;
        }

        // Brand-scoped roles
        $brandIds = $user->brands()->pluck('brands.id')->toArray();
        if (empty($brandIds)) {
            return false;
        }

        $brandRoles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.brand_id', $brandIds)
            ->whereIn('roles.brand_id', $brandIds)
            ->pluck('roles.name')
            ->toArray();

        return !empty(array_intersect($brandRoles, $allowedRoles));
    }
}
