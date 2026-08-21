<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'brand_id',
        'campaign_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'title',
        'message',
        'source',
        'category',
        'status',
        'score',
        'estimated_value',
        'ai_summary',
        'ai_suggested_response',
        'ai_metadata',
        'last_contacted_at',
        'follow_up_at',
        'assigned_to',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'ai_metadata' => 'array',
        'metadata' => 'array',
        'last_contacted_at' => 'datetime',
        'follow_up_at' => 'datetime',
    ];

    /**
     * Get the brand that owns this lead.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the campaign that generated this lead.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the user assigned to this lead.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the interactions for this lead.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(LeadInteraction::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by score.
     */
    public function scopeScore($query, string $score)
    {
        return $query->where('score', $score);
    }

    /**
     * Scope to get leads needing follow-up.
     */
    public function scopeNeedsFollowUp($query, int $days = 3)
    {
        return $query->where('status', '!=', 'won')
            ->where('status', '!=', 'lost')
            ->where(function ($q) use ($days) {
                $q->where('follow_up_at', '<=', now()->addDays($days))
                    ->orWhereNull('follow_up_at');
            });
    }

    /**
     * Get the full name of the lead.
     */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'new' => 'New',
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'proposal' => 'Proposal',
            'negotiation' => 'Negotiation',
            'won' => 'Won',
            'lost' => 'Lost',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get the category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        $labels = [
            'travel' => '🏝️ Travel',
            'software' => '💻 Software',
            'seo' => '🔍 SEO',
            'consulting' => '💼 Consulting',
            'other' => '📋 Other',
        ];

        return $labels[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Get the category color class.
     */
    public function getCategoryColorAttribute(): string
    {
        $colors = [
            'travel' => 'bg-green-100 text-green-800',
            'software' => 'bg-blue-100 text-blue-800',
            'seo' => 'bg-purple-100 text-purple-800',
            'consulting' => 'bg-yellow-100 text-yellow-800',
            'other' => 'bg-gray-100 text-gray-800',
        ];

        return $colors[$this->category] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get the source label.
     */
    public function getSourceLabelAttribute(): string
    {
        $labels = [
            'website' => 'Website',
            'social' => 'Social Media',
            'email' => 'Email',
            'referral' => 'Referral',
            'event' => 'Event',
            'other' => 'Other',
        ];

        return $labels[$this->source] ?? ucfirst($this->source);
    }

    /**
     * Get the score label.
     */
    public function getScoreLabelAttribute(): string
    {
        $labels = [
            'hot' => '🔥 Hot',
            'warm' => '🔥 Warm',
            'cold' => '❄️ Cold',
        ];

        return $labels[$this->score] ?? ucfirst($this->score);
    }

    /**
     * Get the score color class.
     */
    public function getScoreColorAttribute(): string
    {
        $colors = [
            'hot' => 'bg-red-100 text-red-800',
            'warm' => 'bg-orange-100 text-orange-800',
            'cold' => 'bg-blue-100 text-blue-800',
        ];

        return $colors[$this->score] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Check if lead needs follow-up.
     */
    public function needsFollowUp(): bool
    {
        if (in_array($this->status, ['won', 'lost'])) {
            return false;
        }

        if (!$this->follow_up_at) {
            return true;
        }

        return $this->follow_up_at <= now()->addDays(3);
    }

    /**
     * Check if lead is hot.
     */
    public function isHot(): bool
    {
        return $this->score === 'hot';
    }

    /**
     * Check if lead is travel.
     */
    public function isTravel(): bool
    {
        return $this->category === 'travel';
    }

    /**
     * Check if lead is software.
     */
    public function isSoftware(): bool
    {
        return $this->category === 'software';
    }
}