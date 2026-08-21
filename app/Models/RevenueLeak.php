<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueLeak extends Model
{
    protected $fillable = [
        'brand_id',
        'page_url',
        'campaign_name',
        'source',
        'estimated_loss',
        'traffic_loss',
        'conversion_loss',
        'opportunity_description',
        'status',
        'detected_date',
        'resolved_date',
        'metadata',
    ];

    protected $casts = [
        'estimated_loss' => 'decimal:2',
        'traffic_loss' => 'decimal:2',
        'conversion_loss' => 'decimal:2',
        'detected_date' => 'date',
        'resolved_date' => 'date',
        'metadata' => 'array',
    ];

    /**
     * Get the brand that owns this revenue leak.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to only open leaks.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to only resolved leaks.
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Mark the leak as resolved.
     */
    public function markAsResolved(): void
    {
        $this->status = 'resolved';
        $this->resolved_date = now();
        $this->save();
    }
}