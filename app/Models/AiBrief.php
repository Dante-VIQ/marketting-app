<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiBrief extends Model
{
    protected $fillable = [
        'brand_id',
        'brief_date',
        'fingerprint',
        'strategic_diagnosis',
        'estimated_revenue_impact',
        'confidence_score',
        'raw_llm_output',
        'ai_provider',
        'model_used',
        'tokens_used',
        'response_time_ms',
        'is_approved',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'brief_date' => 'date',
        'estimated_revenue_impact' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'raw_llm_output' => 'array',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'tokens_used' => 'integer',
        'response_time_ms' => 'float',
    ];

 /**
     * Get the brand that owns this brief.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the user who approved this brief.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the actions for this brief.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AiAction::class, 'brief_id'); // ← Specify the foreign key
    }

    /**
     * Scope to get today's brief.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('brief_date', now()->toDateString());
    }

    /**
     * Scope to get approved briefs.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Approve the brief.
     */
    public function approve(User $user): void
    {
        $this->is_approved = true;
        $this->approved_at = now();
        $this->approved_by = $user->id;
        $this->save();
    }

    /**
     * Get the number of pending actions.
     */
    public function getPendingActionsCountAttribute(): int
    {
        return $this->actions()->where('status', 'pending')->count();
    }

    /**
     * Get the number of approved actions.
     */
    public function getApprovedActionsCountAttribute(): int
    {
        return $this->actions()->where('status', 'approved')->count();
    }
}