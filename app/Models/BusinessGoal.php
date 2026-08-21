<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessGoal extends Model
{
    protected $fillable = [
        'brand_id',
        'metric_name',
        'target_value',
        'current_value',
        'unit',
        'target_date',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'target_date' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the brand that owns this goal.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to only active goals.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate progress towards the goal.
     */
    public function getProgressAttribute(): float
    {
        if ($this->target_value == 0) {
            return 0;
        }

        return min(100, ($this->current_value / $this->target_value) * 100);
    }
}