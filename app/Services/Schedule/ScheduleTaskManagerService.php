<?php

namespace App\Services\Schedule;

use App\Models\ScheduledTaskStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ScheduleTaskManagerService
{
/**
 * Get all scheduled tasks with their status.
 */
public function getAllTasks(): array
{
    $tasks = $this->getTaskDefinitions();

    foreach ($tasks as &$task) {
        $status = ScheduledTaskStatus::where('task_name', $task['name'])->first();
        if ($status) {
            // Use the new toStatusArray() method
            $task['status'] = $status->toStatusArray();
        }
    }

    return $tasks;
}

    /**
     * Get task definitions.
     */
    protected function getTaskDefinitions(): array
    {
        return [
            [
                'name' => 'analytics-collection',
                'display_name' => 'Analytics Collection',
                'description' => 'Collects analytics from GA4, Search Console, and social media',
                'frequency' => 'daily',
                'scheduled_time' => '05:30',
                'icon' => '📊',
                'phase' => 'Phase 1',
            ],
            [
                'name' => 'brief-generation',
                'display_name' => 'AI Brief Generation',
                'description' => 'Generates daily AI strategic briefs for all brands',
                'frequency' => 'daily',
                'scheduled_time' => '06:00',
                'icon' => '📋',
                'phase' => 'Phase 2',
            ],
            [
                'name' => 'content-generation',
                'display_name' => 'Content Generation',
                'description' => 'Generates content from approved actions',
                'frequency' => 'every_five_minutes',
                'scheduled_time' => null,
                'icon' => '✍️',
                'phase' => 'Phase 4',
            ],
            [
                'name' => 'seo-checks',
                'display_name' => 'SEO Checks',
                'description' => 'Runs daily SEO checks and detects issues',
                'frequency' => 'daily',
                'scheduled_time' => '07:00',
                'icon' => '🔍',
                'phase' => 'Phase 4',
            ],
            [
                'name' => 'lead-processing',
                'display_name' => 'Lead Processing',
                'description' => 'Processes new leads with AI summarization and scoring',
                'frequency' => 'every_fifteen_minutes',
                'scheduled_time' => null,
                'icon' => '👤',
                'phase' => 'Phase 5',
            ],
            [
                'name' => 'campaign-performance',
                'display_name' => 'Campaign Performance',
                'description' => 'Checks campaign performance and ROI',
                'frequency' => 'daily',
                'scheduled_time' => '08:00',
                'icon' => '📈',
                'phase' => 'Phase 5',
            ],
            [
                'name' => 'health-checks',
                'display_name' => 'Health Checks',
                'description' => 'Full system health monitoring',
                'frequency' => 'daily',
                'scheduled_time' => '09:30',
                'icon' => '🛡️',
                'phase' => 'Phase 6',
            ],
            [
                'name' => 'emergency-health-checks',
                'display_name' => 'Emergency Health Checks',
                'description' => 'Hourly health checks when incidents exist',
                'frequency' => 'hourly',
                'scheduled_time' => null,
                'icon' => '🚨',
                'phase' => 'Phase 6',
            ],
            [
                'name' => 'lead-followups',
                'display_name' => 'Lead Follow-ups',
                'description' => 'Sends follow-up emails to incomplete leads',
                'frequency' => 'daily',
                'scheduled_time' => '10:00',
                'icon' => '📧',
                'phase' => 'Phase 6',
            ],
        ];
    }

    /**
     * Get the current status for a task.
     */
    public function getTaskStatus(string $taskName): ?array
    {
        $task = ScheduledTaskStatus::where('task_name', $taskName)->first();
        return $task ? $task->toArray() : null;
    }

    /**
     * Mark a task as running.
     */
    public function markRunning(string $taskName): void
    {
        $task = $this->getOrCreateTask($taskName);
        $task->update([
            'status' => 'running',
            'last_run_at' => now(),
        ]);
    }

    /**
     * Mark a task as successful.
     */
    public function markSuccess(string $taskName, ?string $output = null): void
    {
        $task = $this->getOrCreateTask($taskName);
        $task->update([
            'status' => 'success',
            'last_output' => $output,
            'last_run_at' => now(),
        ]);
        $task->increment('success_count');
    }

    /**
     * Mark a task as failed.
     */
    public function markFailed(string $taskName, string $error): void
    {
        $task = $this->getOrCreateTask($taskName);
        $task->update([
            'status' => 'failed',
            'last_error' => $error,
            'last_run_at' => now(),
        ]);
        $task->increment('failure_count');
    }

    /**
     * Update task status.
     */
    protected function updateStatus(string $taskName, string $status, array $extra = []): ScheduledTaskStatus
    {
        $task = $this->getOrCreateTask($taskName);

        $data = array_merge([
            'status' => $status,
            'last_run_at' => now(),
        ], $extra);

        $task->update($data);

        return $task;
    }

    /**
     * Get or create a task status record.
     */
    protected function getOrCreateTask(string $taskName): ScheduledTaskStatus
    {
        $task = ScheduledTaskStatus::where('task_name', $taskName)->first();

        if (!$task) {
            $task = ScheduledTaskStatus::create([
                'task_name' => $taskName,
                'display_name' => $taskName,
                'frequency' => 'daily',
                'status' => 'idle',
            ]);
        }

        return $task;
    }

    /**
     * Calculate next run time for a task.
     */
    public function calculateNextRun(ScheduledTaskStatus $task): ?Carbon
    {
        $now = now();

        switch ($task->frequency) {
            case 'daily':
                if ($task->scheduled_time) {
                    $time = explode(':', $task->scheduled_time);
                    $next = $now->copy()->setTime((int)$time[0], (int)$time[1], 0);
                    if ($next->isPast()) {
                        $next->addDay();
                    }
                    return $next;
                }
                break;

            case 'hourly':
                return $now->copy()->addHour()->startOfHour();

            case 'every_minute':
                return $now->copy()->addMinute();

            case 'every_five_minutes':
                return $now->copy()->addMinutes(5);

            case 'every_fifteen_minutes':
                return $now->copy()->addMinutes(15);
        }

        return null;
    }

    /**
     * Manually trigger a task.
     */
    public function triggerTask(string $taskName, ?int $userId = null): array
    {
        $jobMap = [
            'analytics-collection' => \App\Jobs\ProcessAllBrandsAnalyticsJob::class,
            'brief-generation' => \App\Jobs\ProcessAllBrandsBriefsJob::class,
            'content-generation' => \App\Jobs\ProcessAllApprovedActionsJob::class,
            'seo-checks' => \App\Jobs\ProcessAllSeoChecksJob::class,
            'lead-processing' => \App\Jobs\ProcessAllBrandsLeadsJob::class,
            'campaign-performance' => \App\Jobs\ProcessAllBrandsCampaignsJob::class,
            'health-checks' => \App\Jobs\ProcessAllHealthChecksJob::class,
            'emergency-health-checks' => \App\Jobs\ProcessAllHealthChecksJob::class,
            'lead-followups' => null, // This is a callback, handled separately
        ];

        $jobClass = $jobMap[$taskName] ?? null;

        if (!$jobClass && $taskName !== 'lead-followups') {
            return [
                'success' => false,
                'message' => "No job found for task: {$taskName}",
            ];
        }

        try {
            $this->markRunning($taskName);

            if ($taskName === 'lead-followups') {
                // Handle callback task
                $qualifier = app(\App\Services\Lead\LeadQualifierService::class);
                $brands = \App\Models\Brand::where('is_active', true)->get();
                foreach ($brands as $brand) {
                    $qualifier->checkForFollowUps($brand);
                }
                $this->markSuccess($taskName, 'Follow-ups processed successfully.');
            } else {
                // Dispatch job
                $jobClass::dispatch();
                $this->markSuccess($taskName, 'Job dispatched successfully.');
            }

            // Log manual trigger
            if ($userId) {
                \App\Models\ScheduleManualTrigger::create([
                    'user_id' => $userId,
                    'job_name' => $taskName,
                    'status' => 'completed',
                ]);
            }

            return [
                'success' => true,
                'message' => "Task '{$taskName}' triggered successfully.",
            ];
        } catch (\Exception $e) {
            $this->markFailed($taskName, $e->getMessage());

            return [
                'success' => false,
                'message' => "Failed to trigger task: " . $e->getMessage(),
            ];
        }
    }
}