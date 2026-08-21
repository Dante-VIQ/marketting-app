<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AhrefsSiteStat extends Model
{
    protected $fillable = [
        'brand_id',
        'domain',
        'domain_rating',
        'url_rating',
        'backlinks',
        'referring_domains',
        'organic_keywords',
        'organic_traffic',
        'traffic_value',
        'tracked_date',
        'metadata',
    ];

    protected $casts = [
        'domain_rating' => 'integer',
        'url_rating' => 'integer',
        'backlinks' => 'integer',
        'referring_domains' => 'integer',
        'organic_keywords' => 'integer',
        'organic_traffic' => 'integer',
        'traffic_value' => 'integer',
        'tracked_date' => 'date',
        'metadata' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('tracked_date', '>=', Carbon::today()->subDays($days));
    }

    public function getDomainRatingColorAttribute(): string
    {
        if ($this->domain_rating >= 80) return 'text-green-600';
        if ($this->domain_rating >= 50) return 'text-blue-600';
        if ($this->domain_rating >= 30) return 'text-yellow-600';
        return 'text-red-600';
    }
}