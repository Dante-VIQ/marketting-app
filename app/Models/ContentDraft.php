<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDraft extends Model
{

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_REVIEW = 'review';
    const STATUS_REVISION = 'revision';
    const STATUS_APPROVED = 'approved';
    const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'brand_id',
        'action_id',
        'title',
        'type',
        'content',
        'excerpt',
        'target_keyword',
        'meta_title',
        'meta_description',
        'seo_data',
        'status',
        'reviewed_at',
        'reviewed_by',
        'published_at',
        'published_url',
        'metadata',
    ];

    protected $casts = [
        'seo_data' => 'array',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /**
     * Get the status label with icon.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_DRAFT => '📄 Draft',
            self::STATUS_REVIEW => '👀 In Review',
            self::STATUS_REVISION => '🔄 Revision Needed',
            self::STATUS_APPROVED => '✅ Approved',
            self::STATUS_PUBLISHED => '🚀 Published',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get the status badge color class.
     */
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            self::STATUS_DRAFT => 'bg-yellow-100 text-yellow-800',
            self::STATUS_REVIEW => 'bg-blue-100 text-blue-800',
            self::STATUS_REVISION => 'bg-orange-100 text-orange-800',
            self::STATUS_APPROVED => 'bg-green-100 text-green-800',
            self::STATUS_PUBLISHED => 'bg-purple-100 text-purple-800',
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Check if draft is in review.
     */
    public function isInReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    /**
     * Check if draft needs revision.
     */
    public function needsRevision(): bool
    {
        return $this->status === self::STATUS_REVISION;
    }

    /**
     * Check if draft is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if draft is published.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Move draft to review.
     */
    public function moveToReview(): void
    {
        $this->status = self::STATUS_REVIEW;
        $this->save();
    }

    /**
     * Move draft to revision.
     */
    public function moveToRevision(string $reason, ?string $notes = null): void
    {
        $this->status = self::STATUS_REVISION;
        $this->metadata = array_merge($this->metadata ?? [], [
            'revision_requested_at' => now()->toDateTimeString(),
                                      'revision_reason' => $reason,
                                      'revision_notes' => $notes,
                                      'revision_count' => ($this->metadata['revision_count'] ?? 0) + 1,
        ]);
        $this->save();
    }

    /**
     * Move draft back to draft (after revision).
     */
    public function moveToDraft(): void
    {
        $this->status = self::STATUS_DRAFT;
        $this->metadata = array_merge($this->metadata ?? [], [
            'revision_completed_at' => now()->toDateTimeString(),
        ]);
        $this->save();
    }

    /**
     * Approve the draft.
     */
    public function approve(?string $notes = null): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->reviewed_at = now();
        $this->reviewed_by = auth()->id();
        $this->metadata = array_merge($this->metadata ?? [], [
            'approved_at' => now()->toDateTimeString(),
                                      'approval_notes' => $notes,
        ]);
        $this->save();
    }
    /**
     * Get the brand that owns this draft.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the action that generated this draft.
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(AiAction::class);
    }

    /**
     * Get the user who reviewed this draft.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the type label with icon.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'blog' => '📝 Blog Post',
            'social' => '📱 Social Post',
            'email' => '✉️ Email',
            'web_copy' => '🌐 Web Copy',
            'newsletter' => '📰 Newsletter',
            'seo_meta' => '🔍 SEO Meta',
            'seo_content' => '📄 SEO Content',
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }


    /**
     * Get the source category from the action.
     */
    public function getSourceCategoryAttribute(): string
    {
        if (!$this->action) {
            return '📋 Manual';
        }

        return $this->action->category_label;
    }

    /**
     * Get the source description from the action.
     */
    public function getSourceDescriptionAttribute(): string
    {
        if (!$this->action) {
            return 'Manually created draft';
        }

        $descriptions = [
            'seo' => "🔍 SEO Fix: {$this->action->target_url}",
            'content' => "📝 Content: {$this->action->title}",
            'social' => "📱 Social Post for: {$this->action->target_platform}",
            'email' => "✉️ Email: {$this->action->title}",
            'web_copy' => "🌐 Web Copy for: {$this->action->target_url}",
            'campaign' => "🎯 Campaign: {$this->action->title}",
            'strategy' => "🧠 Strategy: {$this->action->title}",
            'analytics' => "📊 Analytics: {$this->action->title}",
        ];

        return $descriptions[$this->action->category] ?? $this->action->description ?? $this->action->title;
    }

       /**
     * Get the action priority.
     */
    public function getActionPriorityAttribute(): ?int
    {
        return $this->action?->priority;
    }

        /**
     * Get the action impact.
     */
    public function getActionImpactAttribute(): ?float
    {
        return $this->action?->estimated_impact;
    }

        /**
     * Get the action status.
     */
    public function getActionStatusAttribute(): ?string
    {
        return $this->action?->status;
    }


    /**
     * Check if meta description is the correct length.
     */
    public function getMetaLengthStatusAttribute(): array
    {
        if (!$this->meta_description) {
            return ['valid' => false, 'message' => 'No meta description', 'length' => 0];
        }

        $length = strlen($this->meta_description);
        $isValid = $length >= 140 && $length <= 160;

        return [
            'valid' => $isValid,
            'length' => $length,
            'message' => $isValid ? '✅ Correct length (140-160 chars)' : "⚠️ {$length} chars (should be 140-160)",
        ];
    }

    /**
     * Check if meta title is the correct length.
     */
    public function getMetaTitleLengthStatusAttribute(): array
    {
        if (!$this->meta_title) {
            return ['valid' => false, 'message' => 'No meta title', 'length' => 0];
        }

        $length = strlen($this->meta_title);
        $isValid = $length >= 50 && $length <= 60;

        return [
            'valid' => $isValid,
            'length' => $length,
            'message' => $isValid ? '✅ Correct length (50-60 chars)' : "⚠️ {$length} chars (should be 50-60)",
        ];
    }

    /**
     * Get the word count.
     */
    public function getWordCountAttribute(): int
    {
        return str_word_count(strip_tags($this->content ?? ''));
    }

    /**
     * Get the character count for meta descriptions.
     */
    public function getMetaDescriptionLengthAttribute(): int
    {
        return strlen($this->meta_description ?? '');
    }

    /**
     * Get the character count for meta titles.
     */
    public function getMetaTitleLengthAttribute(): int
    {
        return strlen($this->meta_title ?? '');
    }
}
