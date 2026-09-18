<?php

namespace App\Policies;

use App\Models\AiAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AiActionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'editor', 'viewer']);
    }

    public function view(User $user, AiAction $action): bool
    {
        return $user->hasRole('super-admin')
            || $user->belongsToBrand($action->brand_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'editor']);
    }

    /**
     * Approving an AI action moves it toward execution.
     * Owners, admins, editors, and super-admins can approve.
     */
    public function approve(User $user, AiAction $action): bool
    {
        // Check the user's roles for THIS brand explicitly, ignoring
        // team context which can be stale between requests.
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->where(function ($q) use ($action) {
                $q->where('roles.brand_id', $action->brand_id)
                  ->orWhereNull('roles.brand_id');
            })
            ->pluck('roles.name')
            ->toArray();

        return !empty(array_intersect(
            $roles,
            ['super-admin', 'owner', 'admin', 'editor']
        ));
    }

    public function reject(User $user, AiAction $action): bool
    {
        return $this->approve($user, $action);
    }

    public function delete(User $user, AiAction $action): bool
    {
        if ($user->hasRole('super-admin')) return true;
        return $user->belongsToBrand($action->brand_id) && $user->hasRole('admin');
    }
}