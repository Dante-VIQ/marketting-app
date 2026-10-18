<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorKeyword extends Model
{
    protected $fillable = [
        'brand_id', 'competitor_id', 'keyword', 'position', 'volume', 'difficulty', 'url', 'traffic', 'is_gap', 'tracked_date',
    ];

    protected $casts = ['is_gap' => 'boolean', 'tracked_date' => 'date'];

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
