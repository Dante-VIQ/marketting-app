<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class PageSnapshot extends Model
{
    protected $fillable = [
        'brand_id',
        'action_id',
        'url',
        'page_type',
        'title',
        'headings',
        'content',
        'word_count',
        'readability_score',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_tags',
        'schema_markup',
        'load_time_ms',
        'is_mobile_friendly',
        'broken_links',
        'internal_links',
        'external_links',
        'image_count',
        'images',
        'topics_covered',
        'topics_missing',
        'content_gaps',
        'recommendations',
        'metadata',
        'status',
        'error_message',
        'scraped_at',
    ];

    protected $casts = [
        'headings' => 'array',
        'og_tags' => 'array',
        'schema_markup' => 'array',
        'broken_links' => 'array',
        'internal_links' => 'array',
        'external_links' => 'array',
        'images' => 'array',
        'topics_covered' => 'array',
        'topics_missing' => 'array',
        'content_gaps' => 'array',
        'recommendations' => 'array',
        'metadata' => 'array',
        'scraped_at' => 'datetime',
        'word_count' => 'integer',
        'readability_score' => 'decimal:2',
        'load_time_ms' => 'integer',
        'image_count' => 'integer',
        'is_mobile_friendly' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AiAction::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByUrl($query, string $url)
    {
        return $query->where('url', $url);
    }

    /**
     * Check if a snapshot exists for this URL.
     */
    public static function existsForUrl(string $url, int $brandId): bool
    {
        return self::where('brand_id', $brandId)
        ->where('url', $url)
        ->exists();
    }

    /**
     * Get the latest snapshot for a URL.
     */
    public static function getLatestForUrl(string $url, int $brandId): ?self
    {
        return self::where('brand_id', $brandId)
        ->where('url', $url)
        ->orderBy('created_at', 'desc')
        ->first();
    }

    /**
     * Check if content has changed significantly.
     */
    public function hasContentChanged(array $newData): bool
    {
        // Check if title changed
        if (($this->title ?? '') !== ($newData['title'] ?? '')) {
            return true;
        }

        // Check if content length changed significantly (> 20% change)
        $oldContent = $this->content ?? '';
        $newContent = $newData['content'] ?? '';
        $oldLength = strlen($oldContent);
        $newLength = strlen($newContent);

        if ($oldLength > 0 && $newLength > 0) {
            $changeRatio = abs($newLength - $oldLength) / $oldLength;
            if ($changeRatio > 0.20) { // 20% change threshold
                return true;
            }
        }

        // Check if meta title or description changed
        if (($this->meta_title ?? '') !== ($newData['meta_title'] ?? '')) {
            return true;
        }

        if (($this->meta_description ?? '') !== ($newData['meta_description'] ?? '')) {
            return true;
        }

        return false;
    }
}
