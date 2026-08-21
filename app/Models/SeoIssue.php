<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoIssue extends Model
{
    protected $fillable = [
        'brand_id',
        'page_url',
        'type',
        'severity',
        'description',
        'recommendation',
        'metadata',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the brand that owns this SEO issue.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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
     * Scope to filter by severity.
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to get open issues.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'broken_link' => 'Broken Link',
            'missing_meta' => 'Missing Meta Tags',
            'slow_page' => 'Slow Page',
            'keyword_cannibalization' => 'Keyword Cannibalization',
            'duplicate_content' => 'Duplicate Content',
            'thin_content' => 'Thin Content',
            'missing_alt_text' => 'Missing Alt Text',
            'no_internal_links' => 'No Internal Links',
        ];

        return $labels[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
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
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }
}