<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuardianPolicy extends Model
{
    protected $fillable = [
        'brand_id',
        'name',
        'type',
        'description',
        'rules',
        'severity',
        'is_active',
    ];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the brand that owns this policy.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the incidents for this policy.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(GuardianIncident::class);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get active policies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the severity label.
     */
    public function getSeverityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];

        return $labels[$this->severity] ?? ucfirst($this->severity);
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'content_filter' => 'Content Filter',
            'rate_limit' => 'Rate Limit',
            'safety_check' => 'Safety Check',
            'compliance' => 'Compliance',
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }
}