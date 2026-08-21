<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTaskStatus extends Model
{
    protected $fillable = [
        'task_name',
        'display_name',
        'frequency',
        'scheduled_time',
        'status',
        'last_output',
        'last_error',
        'last_run_at',
        'next_run_at',
        'success_count',
        'failure_count',
        'average_duration_ms',
        'metadata',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'metadata' => 'array',
        'average_duration_ms' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
    ];

    // These are now regular methods, not accessors (no Attribute suffix)
    public function getStatusBadge()
    {
        $badges = [
            'idle' => 'bg-gray-100 text-gray-800',
            'running' => 'bg-yellow-100 text-yellow-800',
            'success' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'skipped' => 'bg-blue-100 text-blue-800',
        ];
        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusIcon()
    {
        $icons = [
            'idle' => '⏸️',
            'running' => '⏳',
            'success' => '✅',
            'failed' => '❌',
            'skipped' => '⏭️',
        ];
        return $icons[$this->status] ?? '❓';
    }

    public function getFrequencyLabel()
    {
        $labels = [
            'daily' => 'Daily',
            'hourly' => 'Hourly',
            'every_minute' => 'Every Minute',
            'every_five_minutes' => 'Every 5 Minutes',
            'every_fifteen_minutes' => 'Every 15 Minutes',
        ];
        return $labels[$this->frequency] ?? $this->frequency;
    }

    public function getNextRunLabel()
    {
        if (!$this->next_run_at) {
            return 'Not scheduled';
        }

        if ($this->next_run_at->isPast()) {
            return 'Overdue';
        }

        return $this->next_run_at->diffForHumans();
    }

    public function getSuccessRate()
    {
        $total = $this->success_count + $this->failure_count;
        if ($total === 0) {
            return 0;
        }
        return round(($this->success_count / $total) * 100, 1);
    }

    // Helper to get all status data as array
    public function toStatusArray(): array
    {
        return [
            'status_badge' => $this->getStatusBadge(),
            'status_icon' => $this->getStatusIcon(),
            'frequency_label' => $this->getFrequencyLabel(),
            'next_run_label' => $this->getNextRunLabel(),
            'success_rate' => $this->getSuccessRate(),
            'status' => $this->status,
            'last_run_at' => $this->last_run_at,
            'next_run_at' => $this->next_run_at,
            'average_duration_ms' => $this->average_duration_ms,
            'last_output' => $this->last_output,
            'last_error' => $this->last_error,
            'success_count' => $this->success_count,
            'failure_count' => $this->failure_count,
        ];
    }
}