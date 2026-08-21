<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelGuide extends Model
{
    protected $fillable = [
        'brand_id',
        'title',
        'slug',
        'destination',
        'duration',
        'description',
        'content',
        'itinerary',
        'tour_packages',
        'affiliate_offers',
        'featured_image',
        'status',
        'views',
        'bookings_generated',
        'revenue_generated',
        'published_at',
        'metadata',
    ];

    protected $casts = [
        'itinerary' => 'array',
        'tour_packages' => 'array',
        'affiliate_offers' => 'array',
        'metadata' => 'array',
        'views' => 'integer',
        'bookings_generated' => 'integer',
        'revenue_generated' => 'decimal:2',
        'published_at' => 'date',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDestination($query, string $destination)
    {
        return $query->where('destination', 'LIKE', "%{$destination}%");
    }

    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'draft' => 'bg-gray-100 text-gray-800',
            'published' => 'bg-green-100 text-green-800',
            'archived' => 'bg-red-100 text-red-800',
        ];
        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }
}