<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TourPackage extends Model
{
    protected $fillable = [
        'brand_id', 'name', 'slug', 'description', 'destination', 'country',
        'duration_days', 'price', 'currency', 'itinerary', 'inclusions',
        'keywords', 'affiliate_network', 'affiliate_url', 'image_url', 'status',
    ];

    protected $casts = [
        'itinerary'   => 'array',
        'inclusions'  => 'array',
        'keywords'    => 'array',
        'price'       => 'decimal:2',
        'duration_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($tour) {
            if (empty($tour->slug)) {
                $tour->slug = Str::slug($tour->name);
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(ContentDraft::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }
}