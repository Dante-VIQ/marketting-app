<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadInteraction extends Model
{
    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'content',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the lead that owns this interaction.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user who created this interaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'email' => 'Email',
            'call' => 'Phone Call',
            'meeting' => 'Meeting',
            'note' => 'Note',
            'task' => 'Task',
            'follow_up' => 'Follow-up',
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }
}