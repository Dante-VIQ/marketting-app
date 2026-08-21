<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateOffer extends Model
{
    protected $fillable = [
        'brand_id',
        'network',
        'offer_id',
        'name',
        'description',
        'category',
        'destination',
        'commission_type',
        'commission_value',
        'currency',
        'price',
        'url',
        'image_url',
        'keywords',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'keywords' => 'array',
        'metadata' => 'array',
        'commission_value' => 'decimal:2',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(BlogAffiliatePlacement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeDestination($query, string $destination)
    {
        return $query->where('destination', 'LIKE', "%{$destination}%");
    }

    public function getCommissionDisplayAttribute(): string
    {
        if ($this->commission_type === 'percentage') {
            return $this->commission_value . '%';
        }
        return $this->currency . ' ' . number_format($this->commission_value, 2);
    }
}