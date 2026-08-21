<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemHealth extends Model
{
    protected $table = 'system_health';

    protected $fillable = [
        'brand_id',
        'component',
        'status',
        'response_time_ms',
        'metadata',
        'checked_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'checked_at' => 'datetime',
        'response_time_ms' => 'float',
    ];

    /**
     * Get the brand that owns this health record.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to filter by component.
     */
    public function scopeComponent($query, string $component)
    {
        return $query->where('component', $component);
    }

    /**
     * Scope to get recent records.
     */
    public function scopeRecent($query, int $minutes = 5)
    {
        return $query->where('checked_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'healthy' => '✅ Healthy',
            'degraded' => '⚠️ Degraded',
            'down' => '❌ Down',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }
}