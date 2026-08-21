<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AhrefsBacklink extends Model
{
    protected $fillable = [
        'brand_id',
        'target_url',
        'source_url',
        'anchor_text',
        'source_domain',
        'source_domain_rating',
        'source_page_title',
        'is_follow',
        'is_nofollow',
        'first_seen_at',
        'last_seen_at',
        'metadata',
    ];

    protected $casts = [
        'is_follow' => 'boolean',
        'is_nofollow' => 'boolean',
        'first_seen_at' => 'date',
        'last_seen_at' => 'date',
        'metadata' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeFollow($query)
    {
        return $query->where('is_follow', true);
    }

    public function scopeNofollow($query)
    {
        return $query->where('is_nofollow', true);
    }

    public function getDomainRatingAttribute(): string
    {
        return $this->source_domain_rating ?? 'N/A';
    }
}