<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentOpportunityTracking extends Model
{
    protected $table = 'agent_opportunity_trackings';

    protected $fillable = [
        'brand_id',
        'stable_key',
        'fingerprint',
        'tracked_date',
        'opportunity_type',
        'opportunity_data',
        'first_seen_at',
        'last_seen_at',
        'times_seen',
        'recurrence_count',
        'last_processed_at',
        'status',
        'action_id',
        'agent_id',
    ];

    protected $casts = [
        'opportunity_data'  => 'array',
        'tracked_date'      => 'date',
        'first_seen_at'     => 'datetime',
        'last_seen_at'      => 'datetime',
        'last_processed_at' => 'datetime',
        'times_seen'        => 'integer',
        'recurrence_count'  => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AiAction::class);
    }

    public function scopeForBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('tracked_date', today());
    }

    public function scopeForStableKey($query, string $stableKey)
    {
        return $query->where('stable_key', $stableKey);
    }
}