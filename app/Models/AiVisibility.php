<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVisibility extends Model
{
    protected $table = 'ai_visibility';

    protected $fillable = [
        'brand_id',
        'score',
        'probes_sent',
        'probes_hit',
        'report',
        'summary',
        'status',
        'error',
        'audited_at',
    ];

    protected $casts = [
        'report'     => 'array',
        'audited_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
