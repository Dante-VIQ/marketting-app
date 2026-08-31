<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentExperience extends Model
{
    protected $fillable = [
        'brand_id',
        'action_name',
        'opportunity_type',
        'severity',
        'context',
        'decision',
        'outcome',
        'confidence',
        'was_autonomous',
        'was_successful',
        'improvement_percentage',
        'duration_seconds',
        'human_feedback',
        'status',
    ];

    protected $casts = [
        'context' => 'array',
        'decision' => 'array',
        'outcome' => 'array',
        'confidence' => 'decimal:4',
        'improvement_percentage' => 'decimal:2',
        'was_autonomous' => 'boolean',
        'was_successful' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('was_successful', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('opportunity_type', $type);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->was_successful) {
            return 1.0;
        }
        return 0.0;
    }

        public function verifications()
    {
        return $this->hasMany(ActionVerification::class);
    }
}