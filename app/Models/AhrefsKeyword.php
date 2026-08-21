<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AhrefsKeyword extends Model
{
    protected $fillable = [
        'brand_id',
        'keyword',
        'target_url',
        'position',
        'search_volume',
        'difficulty',
        'competition',
        'tracked_date',
        'metadata',
    ];

    protected $casts = [
        'position' => 'integer',
        'search_volume' => 'integer',
        'tracked_date' => 'date',
        'metadata' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('tracked_date', '>=', Carbon::today()->subDays($days));
    }

    public function getPositionChangeAttribute(): ?int
    {
        if (!$this->previous_position) {
            return null;
        }
        return $this->previous_position - $this->position;
    }

    public function getDifficultyLabelAttribute(): string
    {
        $labels = [
            'easy' => 'Easy',
            'medium' => 'Medium',
            'hard' => 'Hard',
        ];
        return $labels[$this->difficulty] ?? ucfirst($this->difficulty);
    }
}