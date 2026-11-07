<?php

namespace Tests\Feature;

use App\Models\ScheduleLog;
use App\Services\Schedule\ScheduleMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleMonitorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_logs_include_status_metadata(): void
    {
        ScheduleLog::create([
            'job_name' => 'ProcessAllBrandsAnalyticsJob',
            'job_type' => 'scheduled',
            'status' => 'success',
            'duration_ms' => 1500,
            'started_at' => now()->subSeconds(2),
            'completed_at' => now(),
        ]);

        $data = app(ScheduleMonitorService::class)->getDashboardData();

        $this->assertNotEmpty($data['last_run']);
        $this->assertArrayHasKey('status_icon', $data['last_run']->first()->toArray());
        $this->assertArrayHasKey('status_badge', $data['last_run']->first()->toArray());
        $this->assertArrayHasKey('duration', $data['last_run']->first()->toArray());
        $this->assertSame('✅', $data['last_run']->first()->toArray()['status_icon']);
        $this->assertSame('bg-green-100 text-green-800', $data['last_run']->first()->toArray()['status_badge']);
    }
}
