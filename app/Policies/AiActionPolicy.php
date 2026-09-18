<?php

namespace App\Policies;

use App\Models\AiAction;
use App\Models\User;

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
     * Only admins/editors can approve.
     */
    public function approve(User $user, AiAction $action): bool
    {
        if ($user->hasRole('super-admin')) return true;
        return $user->belongsToBrand($action->brand_id)
            && $user->hasAnyRole(['admin', 'editor']);
    }

    /**
     * Rejecting is like approving – same authority.
     */
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