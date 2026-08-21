<?php

namespace App\Models;

use App\Models\AiBrief;
use App\Models\Brand;
use App\Models\ContentDraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiAction extends Model
{
    protected $fillable = [
        'brand_id',
        'brief_id',      // ← Make sure this matches your table column
        'title',
        'category',
        'description',
        'suggested_content',
        'content_draft',
        'target_platform',
        'target_url',
        'estimated_impact',
        'status',
        'rejection_reason',
        'rejection_notes',
        'priority',
        'approved_at',
        'executed_at',
        'actual_revenue_impact',
    ];

    protected $casts = [
        'estimated_impact' => 'decimal:2',
        'actual_revenue_impact' => 'decimal:2',
        'priority' => 'integer',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    /**
     * Get the brand that owns this action.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the brief that owns this action.
     */
    public function brief(): BelongsTo
    {
        return $this->belongsTo(AiBrief::class, 'brief_id'); // ← Specify the foreign key
    }

    /**
     * Get the page snapshot for this action.
     */
    public function pageSnapshot(): HasOne
    {
        return $this->hasOne(PageSnapshot::class, 'action_id');
    }

        /**
     * Get the content draft for this action.
     */
    public function contentDraft(): HasOne
    {
        return $this->hasOne(ContentDraft::class, 'action_id');
    }
    
    /**
     * Get the category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        $labels = [
            'seo' => '🔍 SEO',
            'content' => '📝 Content',
            'social' => '📱 Social Media',
            'email' => '✉️ Email',
            'web_copy' => '🌐 Web Copy',
            'campaign' => '🎯 Campaign',
            'strategy' => '🧠 Strategy',
            'analytics' => '📊 Analytics',
        ];

        return $labels[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => '⏳ Pending',
            'approved' => '✅ Approved',
            'rejected' => '❌ Rejected',
            'content_generated' => '📄 Content Generated',
            'published' => '🚀 Published',
            'completed' => '✅ Completed',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Check if action has content generated.
     */
    public function hasContentGenerated(): bool
    {
        return $this->status === 'content_generated' || $this->contentDraft()->exists();
    }

    /**
     * Get the content draft if it exists.
     */
    public function getContentDraftAttribute(): ?ContentDraft
    {
        return $this->contentDraft()->first();
    }

    /**
     * Scope to get pending actions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved actions.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected actions.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope to get actions by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get high priority actions.
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', '>=', 4);
    }

    /**
     * Check if action is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if action is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if action is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Get the rejection reason label.
     */
    public function getRejectionReasonLabelAttribute(): ?string
    {
        if (!$this->rejection_reason) {
            return null;
        }

        $labels = [
            'too_short' => 'Too Short',
            'tone_wrong' => 'Tone is Wrong',
            'factually_incorrect' => 'Factually Incorrect',
            'off_brand' => 'Off Brand',
            'duplicate' => 'Duplicate',
            'low_priority' => 'Low Priority',
            'other' => 'Other',
        ];

        return $labels[$this->rejection_reason] ?? ucfirst($this->rejection_reason);
    }
}
