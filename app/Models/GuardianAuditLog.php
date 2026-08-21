<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianAuditLog extends Model
{
    protected $table = 'guardian_audit_logs';

    protected $fillable = [
        'brand_id',
        'user_id',
        'fingerprint',
        'event_type',
        'prompt_sent',
        'raw_response',
        'ai_provider',
        'model_used',
        'tokens_used',
        'response_time_ms',
        'metadata',
    ];

    protected $casts = [
        'prompt_sent' => 'array',
        'raw_response' => 'array',
        'metadata' => 'array',
        'tokens_used' => 'integer',
        'response_time_ms' => 'float',
    ];

    /**
     * Get the brand associated with this log.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the user associated with this log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by fingerprint.
     */
    public function scopeFingerprint($query, string $fingerprint)
    {
        return $query->where('fingerprint', $fingerprint);
    }

    /**
     * Scope to get logs for a specific brand.
     */
    public function scopeForBrand($query, Brand $brand)
    {
        return $query->where('brand_id', $brand->id);
    }

    /**
     * Scope to get recent logs.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}