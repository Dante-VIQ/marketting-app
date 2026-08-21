<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AffiliateData extends Model
{
    protected $fillable = [
        'brand_id',
        'network',
        'date',
        'clicks',
        'bookings',
        'commission_earned',
        'revenue_generated',
        'conversion_rate',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'bookings' => 'integer',
        'commission_earned' => 'decimal:2',
        'revenue_generated' => 'decimal:2',
        'conversion_rate' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('date', '>=', Carbon::today()->subDays($days));
    }
}