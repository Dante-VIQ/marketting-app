<?php

namespace App\Policies;

use App\Models\AiAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AiActionPolicy
{
    public function viewAny(User $user): bool
    {
        // Any role at all gives visibility into the action queue.
        // Direct DB check to avoid the team-context trap.
        return $this->hasAnyRoleAcrossBrands(
            $user,
            ['super-admin', 'owner', 'admin', 'editor', 'viewer']
        );
    }

    public function view(User $user, AiAction $action): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($action->brand_id)) {
            return false;
        }

        return $this->hasBrandRole(
            $user,
            $action->brand_id,
            ['owner', 'admin', 'editor', 'viewer']
        );
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRoleAcrossBrands(
            $user,
            ['super-admin', 'owner', 'admin', 'editor']
        );
    }

    /**
     * Approving an AI action moves it toward execution.
     * Owners, admins, editors, and super-admins can approve.
     */
    public function approve(User $user, AiAction $action): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($action->brand_id)) {
            return false;
        }

        return $this->hasBrandRole(
            $user,
            $action->brand_id,
            ['owner', 'admin', 'editor']
        );
    }

    public function reject(User $user, AiAction $action): bool
    {
        return $this->approve($user, $action);
    }

    public function delete(User $user, AiAction $action): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!$user->belongsToBrand($action->brand_id)) {
            return false;
        }

        return $this->hasBrandRole(
            $user,
            $action->brand_id,
            ['owner', 'admin']
        );
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Check for a global (non-brand-scoped) role by querying the pivot
     * directly. Avoids the team-context trap entirely.
     */
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
     * Check whether the user has any of the given roles for a specific brand.
     * Direct DB query — does not depend on getPermissionsTeamId() being set.
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
        // Global roles (super-admin)
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