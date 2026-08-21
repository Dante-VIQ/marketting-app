<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleLog extends Model
{
    protected $fillable = [
        'job_name',
        'job_type',
        'status',
        'output',
        'error_message',
        'context',
        'duration_ms',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
    ];

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeJob($query, string $jobName)
    {
        return $query->where('job_name', $jobName);
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->duration_ms) {
            return null;
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        }

        return round($this->duration_ms / 1000, 2) . 's';
    }

    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'success' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'running' => 'bg-yellow-100 text-yellow-800',
            'skipped' => 'bg-gray-100 text-gray-800',
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusIconAttribute(): string
    {
        $icons = [
            'success' => '✅',
            'failed' => '❌',
            'running' => '⏳',
            'skipped' => '⏭️',
        ];

        return $icons[$this->status] ?? '❓';
    }
}