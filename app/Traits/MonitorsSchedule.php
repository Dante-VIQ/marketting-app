<?php

namespace App\Traits;

use App\Models\ScheduleLog;
use App\Services\Schedule\ScheduleMonitorService;
use Illuminate\Support\Facades\Log;

trait MonitorsSchedule
{
    protected ?ScheduleLog $scheduleLog = null;

    /**
     * Start monitoring a schedule job.
     */
    protected function startMonitor(array $context = []): void
    {
        $service = app(ScheduleMonitorService::class);
        $jobName = class_basename($this);
        $context['trigger'] = $context['trigger'] ?? 'scheduled';

        $this->scheduleLog = $service->start($jobName, $context['trigger'] ?? 'scheduled', $context);
    }

    /**
     * Mark the job as successful.
     */
    protected function successMonitor(?string $output = null): void
    {
        if ($this->scheduleLog) {
            app(ScheduleMonitorService::class)->success($this->scheduleLog, $output);
        }
    }

    /**
     * Mark the job as failed.
     */
    protected function failMonitor(string $errorMessage, ?string $output = null): void
    {
        if ($this->scheduleLog) {
            app(ScheduleMonitorService::class)->fail($this->scheduleLog, $errorMessage, $output);
        }
    }

    /**
     * Mark the job as skipped.
     */
    protected function skipMonitor(?string $reason = null): void
    {
        if ($this->scheduleLog) {
            app(ScheduleMonitorService::class)->skip($this->scheduleLog, $reason);
        }
    }
}