<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'website_url',
        'domain_type',
        'config',
        'brand_voice',
        'timezone',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'config' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the users who have access to this brand.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_user');
    }

    /**
     * Get roles scoped to this brand.
     */
    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get permissions scoped to this brand.
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Check if a user is the owner of this brand.
     */
    public function isOwner(User $user): bool
    {
        return $user->hasRole('owner', 'web', $this);
    }

    /**
     * Check if a user has admin access (owner or admin).
     */
    public function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin'], 'web', $this);
    }

    /**
     * Get a specific config value.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Scope to only active brands.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to brands of a specific domain type.
     */
    public function scopeDomain($query, string $domainType)
    {
        return $query->where('domain_type', $domainType);
    }
}
