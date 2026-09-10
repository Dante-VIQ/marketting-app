<?php

namespace App\Policies;

use App\Models\ContentDraft;
use App\Models\User;

class ContentDraftPolicy
{
    /**
     * Can the user view any drafts for this brand?
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'editor', 'viewer']);
    }

    /**
     * Can the user view this specific draft?
     */
    public function view(User $user, ContentDraft $draft): bool
    {
        // Owner or member of the brand
        return $user->belongsToBrand($draft->brand_id)
            || $user->hasRole('super-admin');
    }

    /**
     * Only admins and editors can generate content.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'editor']);
    }

    /**
     * Only the original author or an admin can update.
     */
    public function update(User $user, ContentDraft $draft): bool
    {
        if ($user->hasRole('super-admin')) return true;
        if ($draft->created_by === $user->id) return true;
        return $user->belongsToBrand($draft->brand_id) && $user->hasRole('admin');
    }

    /**
     * Only admins can delete.
     */
    public function delete(User $user, ContentDraft $draft): bool
    {
        if ($user->hasRole('super-admin')) return true;
        return $user->belongsToBrand($draft->brand_id) && $user->hasRole('admin');
    }

    /**
     * Only admins can publish (send live).
     */
    public function publish(User $user, ContentDraft $draft): bool
    {
        if ($user->hasRole('super-admin')) return true;
        return $user->belongsToBrand($draft->brand_id) && $user->hasRole('admin');
    }
}