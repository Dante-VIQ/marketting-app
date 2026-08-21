<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'brand_id',
        'name',
        'slug',
        'type',
        'description',
        'budget',
        'spent',
        'revenue',
        'clicks',
        'impressions',
        'leads',
        'conversions',
        'start_date',
        'end_date',
        'status',
        'settings',
        'performance_data',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'revenue' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'settings' => 'array',
        'performance_data' => 'array',
    ];

    /**
     * Get the brand that owns this campaign.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the leads for this campaign.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
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
     * Scope to get active campaigns.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Calculate ROI for the campaign.
     */
    public function getRoiAttribute(): array
    {
        $roi = 0;
        $roiPercentage = 0;

        if ($this->spent > 0) {
            $roi = $this->revenue - $this->spent;
            $roiPercentage = ($roi / $this->spent) * 100;
        }

        $costPerLead = $this->leads > 0 
            ? $this->spent / $this->leads 
            : 0;

        $costPerConversion = $this->conversions > 0 
            ? $this->spent / $this->conversions 
            : 0;

        return [
            'roi' => round($roi, 2),
            'roi_percentage' => round($roiPercentage, 2),
            'cost_per_lead' => round($costPerLead, 2),
            'cost_per_conversion' => round($costPerConversion, 2),
            'conversion_rate' => $this->leads > 0 
                ? round(($this->conversions / $this->leads) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'active' => 'Active',
            'paused' => 'Paused',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'email' => 'Email',
            'social' => 'Social Media',
            'ppc' => 'PPC',
            'seo' => 'SEO',
            'content' => 'Content',
            'affiliate' => 'Affiliate',
            'other' => 'Other',
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }
}