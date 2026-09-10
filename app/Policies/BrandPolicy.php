<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->hasRole('super-admin') || $user->belongsToBrand($brand->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    public function update(User $user, Brand $brand): bool
    {
        if ($user->hasRole('super-admin')) return true;
        return $user->belongsToBrand($brand->id) && $user->hasRole('admin');
    }

    public function delete(User $user, Brand $brand): bool
    {
        if ($user->hasRole('super-admin')) return true;
        // Only the brand owner can delete
        return method_exists($brand, 'isOwner') && $brand->isOwner($user);
    }

    /**
     * Only owners can toggle brand active/inactive.
     */
    public function toggleActive(User $user, Brand $brand): bool
    {
        if ($user->hasRole('super-admin')) return true;
        return method_exists($brand, 'isOwner') && $brand->isOwner($user);
    }
}