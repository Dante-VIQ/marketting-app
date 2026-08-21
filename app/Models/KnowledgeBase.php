<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBase extends Model
{
    protected $table = 'knowledge_base';

    protected $fillable = [
        'brand_id',
        'key',
        'category',
        'content',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the brand that owns this knowledge entry.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to only active entries.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get knowledge by key for a brand.
     */
    public static function getForBrand(Brand $brand, string $key): ?string
    {
        $entry = self::where('brand_id', $brand->id)
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        return $entry ? $entry->content : null;
    }

    /**
     * Get all knowledge for a brand as a key-value array.
     */
    public static function getAllForBrand(Brand $brand): array
    {
        return self::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->get()
            ->pluck('content', 'key')
            ->toArray();
    }
}