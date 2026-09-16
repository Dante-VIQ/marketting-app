<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionVerification extends Model
{
    protected $fillable = [
        'brand_id',
        'action_id',
        'phase',
        'metrics_before',
        'metrics_after',
        'metric_deltas',
        'was_successful',
        'improvement_score',
        'rollback_triggered',
        'rollback_reason',
        'rollback_at',
        'verified_at',
    ];

   protected $casts = [
        'metrics_before'     => 'array',
        'metrics_after'      => 'array',
        'metric_deltas'      => 'array',
        'was_successful'     => 'boolean',
        'improvement_score'  => 'float',
        'rollback_triggered' => 'boolean',
        'rollback_at'        => 'datetime',
        'verified_at'        => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(AgentExperience::class);
    }

        public function action(): BelongsTo
    {
        return $this->belongsTo(AiAction::class);
    }

    public function scopeForBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }
}