<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentIdea extends Model
{
    public const PLATFORMS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X', 'tiktok' => 'TikTok',
        'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp', 'blog' => 'Blog', 'other' => 'Other'];
    public const FORMATS = ['short_video' => 'Short video', 'image' => 'Image', 'carousel' => 'Carousel', 'text' => 'Text post',
        'article' => 'Article', 'story' => 'Story', 'other' => 'Other'];

    protected $fillable = ['brand_id', 'persona_id', 'title', 'topic', 'platform', 'format', 'source', 'source_detail', 'copy', 'status',
        'planned_for', 'posted_at', 'post_url', 'impressions', 'reach', 'engagements', 'clicks', 'shares', 'measured_at',
        'publish_mode', 'scheduled_at', 'approved_at', 'approved_by', 'remote_post_id', 'media_url', 'link_url', 'publish_error', 'image_prompt', 'image_alt', 'site_sessions', 'site_key_events', 'site_measured_at'];

    protected $casts = ['planned_for' => 'date', 'posted_at' => 'datetime', 'measured_at' => 'datetime', 'scheduled_at' => 'datetime', 'approved_at' => 'datetime', 'site_measured_at' => 'datetime'];

    public function persona(): BelongsTo { return $this->belongsTo(Persona::class); }
}
