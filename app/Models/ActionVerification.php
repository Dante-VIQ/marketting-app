<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionVerification extends Model
{
    protected $fillable = [
        'brand_id',
        'action_name',
        'opportunity_type',
        'experience_id',
        'before_metrics',
        'after_metrics',
        'improvement_percentage',
        'was_successful',
        'status',
        'verification_notes',
        'verified_at',
    ];

    protected $casts = [
        'before_metrics' => 'array',
        'after_metrics' => 'array',
        'improvement_percentage' => 'decimal:2',
        'was_successful' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(AgentExperience::class);
    }
}