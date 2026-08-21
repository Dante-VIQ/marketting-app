<?php

namespace App\Livewire;

use App\Models\ScheduleLog;
use App\Models\ScheduleManualTrigger;
use App\Services\Schedule\ScheduleMonitorService;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\ScheduledTaskStatus;
use App\Services\Schedule\ScheduleTaskManagerService;

new class extends Component {
    public $logs = [];
    public $jobStatuses = [];
    public $manualTriggers = [];
    public $selectedJob = null;
    public $tasks = [];
    public $taskStatuses = [];
    public $summary = [
        'total' => 0,
        'running' => 0,
        'success' => 0,
        'failed' => 0,
    ];
    public $selectedTask = null;


    protected $listeners = ['refreshScheduleData' => 'loadData', 'refreshSchedules' => 'loadTasks'];

    public function mount()
    {
        $this->loadData();
        $this->loadTasks();
    }

    public function loadTasks()
    {
        $service = app(ScheduleTaskManagerService::class);
        $this->tasks = $service->getAllTasks();

        // Get statuses
        $this->taskStatuses = ScheduledTaskStatus::all()
            ->keyBy('task_name')
            ->toArray();

        // Calculate summary
        $this->summary['total'] = count($this->tasks);
        $this->summary['running'] = ScheduledTaskStatus::where('status', 'running')->count();
        $this->summary['success'] = ScheduledTaskStatus::where('status', 'success')->count();
        $this->summary['failed'] = ScheduledTaskStatus::where('status', 'failed')->count();
    }

    public function triggerTask($taskName)
    {
        $user = Auth::user();

        $service = app(ScheduleTaskManagerService::class);
        $result = $service->triggerTask($taskName, $user->id);

        $this->loadTasks();

        if ($result['success']) {
            session()->flash('message', $result['message']);
        } else {
            session()->flash('error', $result['message']);
        }
    }

    public function viewTaskDetails($taskName)
    {
        $this->selectedTask = $taskName;
        $this->dispatch('show-task-details');
    }
    public function loadData()
    {
        $service = app(ScheduleMonitorService::class);
        $data = $service->getDashboardData();

        $this->logs = $data['last_run']->toArray();
        $this->summary = [
            'today_total' => $data['today_total'],
            'today_success' => $data['today_success'],
            'today_failed' => $data['today_failed'],
            'running' => $data['running'],
        ];
        $this->jobStatuses = $data['job_status'];
        $this->manualTriggers = $service->getManualTriggers();
    }

    /**
     * Manually trigger a job.
     */
    public function triggerJob($jobName)
    {
        $user = Auth::user();

        // Log the manual trigger
        $trigger = ScheduleManualTrigger::create([
            'user_id' => $user->id,
            'job_name' => $jobName,
            'status' => 'queued',
        ]);

        // Dispatch the job
        try {
            $jobClass = $this->getJobClass($jobName);

            if ($jobClass) {
                $jobClass::dispatch();
                $trigger->update(['status' => 'completed']);
                $this->dispatch('refreshScheduleData');
                session()->flash('message', "Job '{$jobName}' triggered successfully.");
            } else {
                throw new \Exception("Job class not found for: {$jobName}");
            }
        } catch (\Exception $e) {
            $trigger->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            session()->flash('error', "Failed to trigger job: {$e->getMessage()}");
        }

        $this->loadData();
    }

    /**
     * Get job class from job name.
     */
    protected function getJobClass($jobName): ?string
    {
        $map = [
            'ProcessAllBrandsAnalyticsJob' => \App\Jobs\ProcessAllBrandsAnalyticsJob::class,
            'ProcessAllBrandsBriefsJob' => \App\Jobs\ProcessAllBrandsBriefsJob::class,
            'ProcessAllApprovedActionsJob' => \App\Jobs\ProcessAllApprovedActionsJob::class,
            'ProcessAllSeoChecksJob' => \App\Jobs\ProcessAllSeoChecksJob::class,
            'ProcessAllBrandsLeadsJob' => \App\Jobs\ProcessAllBrandsLeadsJob::class,
            'ProcessAllBrandsCampaignsJob' => \App\Jobs\ProcessAllBrandsCampaignsJob::class,
            'ProcessAllHealthChecksJob' => \App\Jobs\ProcessAllHealthChecksJob::class,
        ];

        return $map[$jobName] ?? null;
    }

};
?>

<div>
    <!-- Flash Messages -->
    @if(session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('message') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            {{ session('error') }}
        </div>
    @endif
    <div>
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Today's Runs</p>
                        <p class="text-2xl font-bold text-blue-600">{{ $summary['today_total'] ?? 0 }}</p>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Successful</p>
                        <p class="text-2xl font-bold text-green-600">{{ $summary['today_success'] ?? 0 }}</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-full">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Failed</p>
                        <p class="text-2xl font-bold text-red-600">{{ $summary['today_failed'] ?? 0 }}</p>
                    </div>
                    <div class="p-3 bg-red-50 rounded-full">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Running</p>
                        <p class="text-2xl font-bold text-yellow-600">{{ $summary['running'] ?? 0 }}</p>
                    </div>
                    <div class="p-3 bg-yellow-50 rounded-full">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h5M4 20l5-5m7-5v5h5M20 4l-5 5" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Statuses & Manual Triggers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Job Statuses -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">📋 Job Status</h2>
                </div>
                <div class="p-6">
                    @if(empty($jobStatuses))
                        <p class="text-gray-500 text-center py-4">No job data available.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($jobStatuses as $jobName => $status)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-medium text-gray-900">{{ $jobName }}</span>
                                            <span class="px-2 py-1 text-xs rounded-full 
                                                    {{ $status['status'] === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $status['status'] === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                                    {{ $status['status'] === 'running' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                    {{ $status['status'] === 'skipped' ? 'bg-gray-100 text-gray-800' : '' }}
                                                ">
                                                {{ ucfirst($status['status']) }}
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                Success Rate: {{ $status['success_rate'] }}%
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-3 text-xs text-gray-500 mt-1">
                                            <span>Last run: {{ $status['last_run'] ?? 'Never' }}</span>
                                            @if($status['duration'])
                                                <span>Duration: {{ $status['duration'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <button wire:click="triggerJob('{{ $jobName }}')"
                                        wire:confirm="Are you sure you want to run {{ $jobName }} manually?"
                                        class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                        ▶️ Run Now
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Runs -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">🔄 Recent Runs</h2>
                </div>
                <div class="p-6 max-h-96 overflow-y-auto">
                    @if(empty($logs))
                        <p class="text-gray-500 text-center py-4">No runs recorded yet.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($logs as $log)
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-sm">
                                    <div class="flex items-center space-x-3">
                                        <span>{{ $log['status_icon'] }}</span>
                                        <span class="font-medium text-gray-700">{{ $log['job_name'] }}</span>
                                        <span class="px-2 py-0.5 text-xs rounded-full {{ $log['status_badge'] }}">
                                            {{ ucfirst($log['status']) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-3 text-xs text-gray-500">
                                        @if($log['duration_ms'])
                                            <span>{{ $log['duration'] }}</span>
                                        @endif
                                        <span>{{ \Carbon\Carbon::parse($log['created_at'])->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Manual Triggers Log -->
        <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">👤 Manual Triggers</h2>
            </div>
            <div class="p-6 max-h-64 overflow-y-auto">
                @if(empty($manualTriggers))
                    <p class="text-gray-500 text-center py-4">No manual triggers yet.</p>
                @else
                    <table class="min-w-full">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="pb-2 font-semibold">Job</th>
                                <th class="pb-2 font-semibold">Triggered By</th>
                                <th class="pb-2 font-semibold">Status</th>
                                <th class="pb-2 font-semibold">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($manualTriggers as $trigger)
                                <tr>
                                    <td class="py-2 text-sm font-medium text-gray-700">{{ $trigger['job_name'] }}</td>
                                    <td class="py-2 text-sm text-gray-600">{{ $trigger['user'] }}</td>
                                    <td class="py-2 text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $trigger['status_badge'] }}">
                                            {{ $trigger['status_icon'] }} {{ ucfirst($trigger['status']) }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-sm text-gray-500">{{ $trigger['created_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

<div>
    <!-- Flash Messages -->
    @if(session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('message') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Tasks</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $summary['total'] ?? 0 }}</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Running</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ $summary['running'] ?? 0 }}</p>
                </div>
                <div class="p-3 bg-yellow-50 rounded-full">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 20l5-5m7-5v5h5M20 4l-5 5"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Successful</p>
                    <p class="text-2xl font-bold text-green-600">{{ $summary['success'] ?? 0 }}</p>
                </div>
                <div class="p-3 bg-green-50 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Failed</p>
                    <p class="text-2xl font-bold text-red-600">{{ $summary['failed'] ?? 0 }}</p>
                </div>
                <div class="p-3 bg-red-50 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Task List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📋 Scheduled Tasks</h2>
            <p class="text-sm text-gray-500">All scheduled tasks and their current status</p>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="pb-3 font-semibold">Task</th>
                            <th class="pb-3 font-semibold">Frequency</th>
                            <th class="pb-3 font-semibold">Next Run</th>
                            <th class="pb-3 font-semibold">Status</th>
                            <th class="pb-3 font-semibold">Last Run</th>
                            <th class="pb-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($tasks as $task)
                            @php
                                $status = $task['status'] ?? null;
                            @endphp
                            <tr>
                                <td class="py-3">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-lg">{{ $task['icon'] }}</span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $task['display_name'] }}</p>
                                            <p class="text-xs text-gray-500">{{ $task['description'] }}</p>
                                            <span class="text-xs text-gray-400">{{ $task['phase'] }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-sm text-gray-700">
                                    {{ $status['frequency_label'] ?? $task['frequency'] }}
                                    @if($task['scheduled_time'])
                                        <span class="text-xs text-gray-400 block">{{ $task['scheduled_time'] }}</span>
                                    @endif
                                </td>
                                <td class="py-3 text-sm text-gray-700">
                                    @if($status && isset($status['next_run_at']) && $status['next_run_at'])
                                        <span class="text-green-600">{{ \Carbon\Carbon::parse($status['next_run_at'])->diffForHumans() }}</span>
                                        <span class="text-xs text-gray-400 block">{{ \Carbon\Carbon::parse($status['next_run_at'])->format('H:i') }}</span>
                                    @else
                                        <span class="text-gray-400">Not scheduled</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    @if($status)
                                        <span class="px-2 py-1 text-xs rounded-full {{ $status['status_badge'] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $status['status_icon'] ?? '⏸️' }} {{ ucfirst($status['status'] ?? 'idle') }}
                                        </span>
                                        @if(isset($status['success_rate']) && $status['success_rate'] > 0)
                                            <span class="text-xs text-gray-400 block mt-1">
                                                Success Rate: {{ $status['success_rate'] }}%
                                            </span>
                                        @endif
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                            ⏸️ Idle
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 text-sm text-gray-500">
                                    @if($status && isset($status['last_run_at']) && $status['last_run_at'])
                                        {{ \Carbon\Carbon::parse($status['last_run_at'])->diffForHumans() }}
                                        @if(isset($status['average_duration_ms']) && $status['average_duration_ms'] > 0)
                                            <span class="text-xs text-gray-400 block">
                                                Avg: {{ $status['average_duration_ms'] }}ms
                                            </span>
                                        @endif
                                    @else
                                        Never
                                    @endif
                                </td>
                                <td class="py-3">
                                    <button wire:click="triggerTask('{{ $task['name'] }}')" 
                                            wire:confirm="Are you sure you want to run '{{ $task['display_name'] }}' manually?"
                                            class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                        ▶️ Run Now
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>