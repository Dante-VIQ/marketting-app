<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianIncident extends Model
{
    protected $fillable = [
        'brand_id',
        'policy_id',
        'type',
        'severity',
        'description',
        'context',
        'metadata',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'context' => 'array',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the brand that owns this incident.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the policy that triggered this incident.
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(GuardianPolicy::class);
    }

    /**
     * Get the user who resolved this incident.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get open incidents.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to filter by severity.
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Get the severity label.
     */
    public function getSeverityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];

        return $labels[$this->severity] ?? ucfirst($this->severity);
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'open' => 'Open',
            'investigating' => 'Investigating',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Resolve the incident.
     */
    public function resolve(User $user, ?string $notes = null): void
    {
        $this->status = 'resolved';
        $this->resolved_at = now();
        $this->resolved_by = $user->id;
        $this->metadata = array_merge($this->metadata ?? [], [
            'resolution_notes' => $notes,
            'resolved_at' => now()->toDateTimeString(),
        ]);
        $this->save();
    }
}