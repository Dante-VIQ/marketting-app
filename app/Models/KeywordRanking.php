<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordRanking extends Model
{
    protected $fillable = [
        'brand_id',
        'keyword',
        'page_url',
        'position',
        'previous_position',
        'search_volume',
        'difficulty',
        'metadata',
        'tracked_date',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tracked_date' => 'date',
    ];

    /**
     * Get the brand that owns this keyword ranking.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to filter by keyword.
     */
    public function scopeKeyword($query, string $keyword)
    {
        return $query->where('keyword', $keyword);
    }

    /**
     * Scope to get latest rankings.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('tracked_date', 'desc');
    }

    /**
     * Get the difficulty label.
     */
    public function getDifficultyLabelAttribute(): string
    {
        $labels = [
            'easy' => 'Easy',
            'medium' => 'Medium',
            'hard' => 'Hard',
        ];

        return $labels[$this->difficulty] ?? ucfirst($this->difficulty);
    }

    /**
     * Get the position change.
     */
    public function getPositionChangeAttribute(): ?int
    {
        if (!$this->previous_position) {
            return null;
        }

        return $this->previous_position - $this->position;
    }

    /**
     * Get the position trend.
     */
    public function getPositionTrendAttribute(): string
    {
        $change = $this->position_change;

        if ($change === null) {
            return 'new';
        }

        if ($change > 0) {
            return 'up';
        }

        if ($change < 0) {
            return 'down';
        }

        return 'stable';
    }
}