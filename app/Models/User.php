<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\PermissionRegistrar;

class User extends Authenticatable
{
    use HasRoles, TwoFactorAuthenticatable, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'active_brand_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * Get all brands the user has access to.
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_user');
    }

    /**
     * Get the user's currently active brand.
     */
    public function activeBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'active_brand_id');
    }

    /**
     * Switch the active brand for this user.
     */
    public function switchBrand(Brand $brand): void
    {
        if (!$this->hasAccessTo($brand)) {
            throw new \Exception('User does not have access to this brand.');
        }

        $this->active_brand_id = $brand->id;
        $this->save();

        $this->setBrandContext($brand);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Set the brand context for Spatie permission checks.
     */
    public function setBrandContext(Brand $brand): void
    {
        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->setPermissionsTeamId($brand->id);
    }

    /**
     * Get the current brand ID for permission checks.
     */
    public function getBrandContext(): ?int
    {
        return $this->active_brand_id;
    }

    /**
     * Check if user has access to a specific brand.
     */
    public function hasAccessTo(Brand $brand): bool
    {
        return $this->brands()->where('brand_id', $brand->id)->exists();
    }

    /**
     * Check if user can manage a specific brand.
     */
    public function canManageBrand(Brand $brand): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        return $this->hasPermissionTo('manage-brand', 'web', $brand);
    }

    /**
     * Scope to get users for a specific brand.
     */
    public function scopeForBrand($query, Brand $brand)
    {
        return $query->whereHas('brands', function ($q) use ($brand) {
            $q->where('brand_id', $brand->id);
        });
    }

    /**
     * Override the default guard for Spatie Permission.
     */
    protected function getDefaultGuardName(): string
    {
        return 'web';
    }

    // === PHASE 5 RELATIONSHIPS ===

    /**
     * Get the leads assigned to this user.
     */
    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    /**
     * Get the lead interactions created by this user.
     */
    public function leadInteractions(): HasMany
    {
        return $this->hasMany(LeadInteraction::class);
    }

    /**
     * Get the briefs approved by this user.
     */
    public function approvedBriefs(): HasMany
    {
        return $this->hasMany(AiBrief::class, 'approved_by');
    }

    /**
     * Get the content drafts reviewed by this user.
     */
    public function reviewedDrafts(): HasMany
    {
        return $this->hasMany(ContentDraft::class, 'reviewed_by');
    }

    /**
     * Get the audit logs for this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(GuardianAuditLog::class);
    }
}