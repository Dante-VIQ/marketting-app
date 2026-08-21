<?php

namespace App\Services\Schedule;

use App\Models\ScheduleLog;
use App\Models\ScheduleManualTrigger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduleMonitorService
{
    /**
     * Start logging a schedule run.
     */
    public function start(string $jobName, string $jobType = 'scheduled', array $context = []): ScheduleLog
    {
        $context['started_by'] = $context['started_by'] ?? 'system';
        $context['trigger'] = $context['trigger'] ?? $jobType;

        return ScheduleLog::create([
            'job_name' => $jobName,
            'job_type' => $jobType,
            'status' => 'running',
            'context' => $context,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark a schedule run as successful.
     */
    public function success(ScheduleLog $log, ?string $output = null): void
    {
        $log->update([
            'status' => 'success',
            'output' => $output,
            'completed_at' => now(),
            'duration_ms' => $this->calculateDuration($log),
        ]);

        Log::info('Schedule job completed successfully', [
            'job_name' => $log->job_name,
            'duration_ms' => $log->duration_ms,
        ]);
    }

    /**
     * Mark a schedule run as failed.
     */
    public function fail(ScheduleLog $log, string $errorMessage, ?string $output = null): void
    {
        $log->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'output' => $output,
            'completed_at' => now(),
            'duration_ms' => $this->calculateDuration($log),
        ]);

        Log::error('Schedule job failed', [
            'job_name' => $log->job_name,
            'error' => $errorMessage,
            'duration_ms' => $log->duration_ms,
        ]);
    }

    /**
     * Skip a schedule run.
     */
    public function skip(ScheduleLog $log, ?string $reason = null): void
    {
        $log->update([
            'status' => 'skipped',
            'output' => $reason ?? 'Job was skipped',
            'completed_at' => now(),
            'duration_ms' => 0,
        ]);

        Log::info('Schedule job skipped', [
            'job_name' => $log->job_name,
            'reason' => $reason,
        ]);
    }

    /**
     * Calculate duration between start and completion.
     */
    protected function calculateDuration(ScheduleLog $log): int
    {
        if (!$log->started_at || !$log->completed_at) {
            return 0;
        }

        return $log->started_at->diffInMilliseconds($log->completed_at);
    }

    /**
     * Get schedule logs for the dashboard.
     */
    public function getDashboardData(): array
    {
        $lastRun = ScheduleLog::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $today = ScheduleLog::whereDate('created_at', today())
            ->count();

        $failed = ScheduleLog::where('status', 'failed')
            ->whereDate('created_at', today())
            ->count();

        $success = ScheduleLog::where('status', 'success')
            ->whereDate('created_at', today())
            ->count();

        $running = ScheduleLog::where('status', 'running')
            ->count();

        $jobs = ScheduleLog::select('job_name')
            ->distinct()
            ->pluck('job_name')
            ->toArray();

        return [
            'last_run' => $lastRun,
            'today_total' => $today,
            'today_success' => $success,
            'today_failed' => $failed,
            'running' => $running,
            'jobs' => $jobs,
            'job_status' => $this->getJobStatuses($jobs),
        ];
    }

    /**
     * Get status summary for each job.
     */
    protected function getJobStatuses(array $jobs): array
    {
        $statuses = [];

        foreach ($jobs as $job) {
            $latest = ScheduleLog::where('job_name', $job)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latest) {
                $statuses[$job] = [
                    'status' => $latest->status,
                    'last_run' => $latest->created_at->diffForHumans(),
                    'duration' => $latest->duration,
                    'success_rate' => $this->calculateSuccessRate($job),
                ];
            }
        }

        return $statuses;
    }

    /**
     * Calculate success rate for a job.
     */
    protected function calculateSuccessRate(string $jobName): float
    {
        $total = ScheduleLog::where('job_name', $jobName)->count();

        if ($total === 0) {
            return 0;
        }

        $success = ScheduleLog::where('job_name', $jobName)
            ->where('status', 'success')
            ->count();

        return round(($success / $total) * 100, 1);
    }

    /**
     * Get manual triggers for the dashboard.
     */
    public function getManualTriggers(): array
    {
        return ScheduleManualTrigger::orderBy('created_at', 'desc')
            ->limit(20)
            ->with('user')
            ->get()
            ->map(function ($trigger) {
                return [
                    'id' => $trigger->id,
                    'job_name' => $trigger->job_name,
                    'status' => $trigger->status,
                    'status_badge' => $trigger->status_badge,
                    'status_icon' => $trigger->status_icon,
                    'user' => $trigger->user->name ?? 'Unknown',
                    'created_at' => $trigger->created_at->diffForHumans(),
                    'output' => $trigger->output,
                    'error_message' => $trigger->error_message,
                ];
            })
            ->toArray();
    }
}