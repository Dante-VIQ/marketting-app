<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogAffiliatePlacement extends Model
{
    protected $fillable = [
        'blog_post_id',
        'affiliate_offer_id',
        'placement_type',
        'anchor_text',
        'url',
        'clicks',
        'bookings',
        'commission_earned',
        'revenue_generated',
        'metadata',
    ];

    protected $casts = [
        'clicks' => 'integer',
        'bookings' => 'integer',
        'commission_earned' => 'decimal:2',
        'revenue_generated' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    public function affiliateOffer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class);
    }

    public function getPlacementTypeLabelAttribute(): string
    {
        $labels = [
            'in_content' => 'In Content',
            'sidebar' => 'Sidebar',
            'banner' => 'Banner',
            'cta' => 'Call to Action',
        ];
        return $labels[$this->placement_type] ?? ucfirst($this->placement_type);
    }
}