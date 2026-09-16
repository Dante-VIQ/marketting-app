<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfidenceCalibration extends Model
{
    protected $fillable = [
        'brand_id',
        'confidence_bucket',
        'opportunity_type',
        'action_name',
        'total_predictions',
        'successful_predictions',
        'actual_accuracy',
        'last_updated_at',
    ];

    protected $casts = [
        'confidence_bucket'        => 'integer',
        'total_predictions'        => 'integer',
        'successful_predictions'   => 'integer',
        'actual_accuracy'          => 'float',
        'last_updated_at'          => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeForBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('opportunity_type', $type);
    }

    // Static helper: convert 0.0–1.0 to bucket 0..10
    public static function bucket(float $confidence): int
    {
        return min(10, max(0, (int) floor($confidence * 10)));
    }
}