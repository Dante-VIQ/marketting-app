<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Competitor extends Model
{
    protected $fillable = ['brand_id', 'name', 'domain', 'is_active', 'notes'];

    protected $casts = ['is_active' => 'boolean'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(CompetitorSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(CompetitorSnapshot::class)->latestOfMany('captured_on');
    }
}
