<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVisibilityAudit extends Model
{
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

    /** Convenience: percentage mention rate */
    public function mentionRate(): float
    {
        if ($this->probes_sent === 0) {
            return 0.0;
        }
        return round(($this->probes_hit / $this->probes_sent) * 100, 1);
    }

    /** Human-readable score tier */
    public function tier(): string
    {
        return match (true) {
            $this->score >= 70 => 'Strong',
            $this->score >= 40 => 'Growing',
            $this->score >= 15 => 'Emerging',
            default            => 'Not visible',
        };
    }

    public function tierColor(): string
    {
        return match ($this->tier()) {
            'Strong'      => 'emerald',
            'Growing'     => 'sky',
            'Emerging'    => 'amber',
            default       => 'red',
        };
    }
}
