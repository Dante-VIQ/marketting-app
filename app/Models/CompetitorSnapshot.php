<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorSnapshot extends Model
{
    protected $fillable = [
        'competitor_id', 'brand_id', 'captured_on', 'status', 'error', 'homepage_title', 'meta_description',
        'h1', 'sitemap_url_count', 'posts_last_30d', 'cadence_basis', 'latest_post_at', 'feed_url',
        'top_terms', 'recent_posts',
    ];

    protected $casts = [
        'captured_on'    => 'date',
        'latest_post_at' => 'datetime',
        'top_terms'      => 'array',
        'recent_posts'   => 'array',
    ];

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
