<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends Model
{
    protected $fillable = [
        'brand_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'author',
        'tags',
        'categories',
        'meta_title',
        'meta_description',
        'seo_data',
        'views',
        'leads_generated',
        'bookings_generated',
        'revenue_generated',
        'average_time_on_page',
        'bounce_rate',
        'status',
        'source',
        'action_id',
        'published_at',
        'last_updated_at',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'categories' => 'array',
        'seo_data' => 'array',
        'metadata' => 'array',
        'views' => 'integer',
        'leads_generated' => 'integer',
        'bookings_generated' => 'integer',
        'revenue_generated' => 'decimal:2',
        'average_time_on_page' => 'decimal:2',
        'bounce_rate' => 'decimal:2',
        'published_at' => 'date',
        'last_updated_at' => 'date',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}