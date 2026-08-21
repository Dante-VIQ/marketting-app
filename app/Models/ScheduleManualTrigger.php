<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleManualTrigger extends Model
{
    protected $fillable = [
        'user_id',
        'job_name',
        'status',
        'output',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'queued' => 'bg-gray-100 text-gray-800',
            'running' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusIconAttribute(): string
    {
        $icons = [
            'queued' => '⏳',
            'running' => '⚡',
            'completed' => '✅',
            'failed' => '❌',
        ];

        return $icons[$this->status] ?? '❓';
    }
}