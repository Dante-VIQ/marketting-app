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
        'content_hash',
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
     * Compute the canonical content hash for a raw HTML string.
     *
     * SHA-256 of the raw response body — deterministic, cheap, collision-safe.
     * Used by RunPublicScanJob and PageScannerService to skip AI analysis when
     * the page hasn't actually changed between scans.
     */
    public static function computeHash(string $rawHtml): string
    {
        return hash('sha256', $rawHtml);
    }

    /**
     * Check whether a new raw HTML hash differs from the stored one.
     *
     * Returns true  → content changed, run the full AI pipeline.
     * Returns false → content identical, skip AI, reuse existing analysis.
     *
     * Falls back to the legacy fuzzy check when no hash is stored (old snapshots
     * that pre-date the content_hash column) so nothing breaks on upgrade.
     */
    public function hasContentChanged(array $newData): bool
    {
        // ── Fast path: exact hash comparison ────────────────────────────────
        $newHash = $newData['content_hash'] ?? null;
        if ($newHash !== null && $this->content_hash !== null) {
            return $newHash !== $this->content_hash;
        }

        // ── Legacy fallback: fuzzy heuristics for pre-hash snapshots ─────────
        if (($this->title ?? '') !== ($newData['title'] ?? '')) {
            return true;
        }

        $oldLen = strlen($this->content ?? '');
        $newLen = strlen($newData['content'] ?? '');
        if ($oldLen > 0 && $newLen > 0 && abs($newLen - $oldLen) / $oldLen > 0.20) {
            return true;
        }

        if (($this->meta_title ?? '') !== ($newData['meta_title'] ?? '')) {
            return true;
        }

        if (($this->meta_description ?? '') !== ($newData['meta_description'] ?? '')) {
            return true;
        }

        return false;
    }
}
