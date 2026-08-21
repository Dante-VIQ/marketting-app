<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSnapshot extends Model
{
    protected $fillable = [
        'brand_id',
        'date',
        'source',
        'metric',
        'dimension',
        'value',
        'change_wo_w',
        'change_mo_m',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'value' => 'decimal:2',
        'change_wo_w' => 'decimal:2',
        'change_mo_m' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the brand that owns this snapshot.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to filter by source.
     */
    public function scopeSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to filter by metric.
     */
    public function scopeMetric($query, string $metric)
    {
        return $query->where('metric', $metric);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get data for the last N days.
     */
    public function scopeLastDays($query, int $days)
    {
        return $query->where('date', '>=', now()->subDays($days)->toDateString());
    }
}