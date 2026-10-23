<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the scheduler rules found in the audit. Reads source files, so it needs no framework boot. */
class SchedulerHygieneTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_no_task_uses_the_24_hour_default_overlap_lock(): void
    {
        $kernel = file_get_contents($this->root() . '/app/Console/Kernel.php');
        $kernel = preg_replace('#^\s*//.*$#m', '', $kernel);   // ignore commented-out examples
        $this->assertStringNotContainsString('withoutOverlapping()', $kernel,
            'A crashed run would block the task for 24 hours. Give withoutOverlapping() an expiry in minutes.');
    }

    public function test_queue_retry_after_exceeds_every_job_timeout(): void
    {
        $queue = file_get_contents($this->root() . '/config/queue.php');
        $this->assertSame(1, preg_match("/DB_QUEUE_RETRY_AFTER', (\d+)/", $queue, $m));
        $retryAfter = (int) $m[1];

        $longest = 0;
        foreach (glob($this->root() . '/app/Jobs/*.php') as $file) {
            if (preg_match('/public int \$timeout = (\d+);/', file_get_contents($file), $t)) {
                $longest = max($longest, (int) $t[1]);
            }
        }
        $this->assertGreaterThan(0, $longest);
        $this->assertGreaterThan($longest, $retryAfter, 'A job slower than retry_after is handed to a second worker and runs twice.');
    }

    public function test_meta_syncs_run_before_the_daily_brief(): void
    {
        $kernel = file_get_contents($this->root() . '/app/Console/Kernel.php');
        $this->assertSame(1, preg_match("/meta:sync-results'\)\s*->dailyAt\('(\d\d):(\d\d)'/", $kernel, $r));
        $this->assertSame(1, preg_match("/meta:sync-data --only=ads'\)\s*->dailyAt\('(\d\d):(\d\d)'/", $kernel, $a));
        $this->assertSame(1, preg_match("/name\('brief-generation'\)\s*->dailyAt\('(\d\d):(\d\d)'/", $kernel, $b));
        $mins = fn (array $m) => (int) $m[1] * 60 + (int) $m[2];
        $this->assertLessThan($mins($b), $mins($r));
        $this->assertLessThan($mins($b), $mins($a));
    }

    public function test_python_service_never_builds_a_shell_string(): void
    {
        $src = file_get_contents($this->root() . '/app/Services/Python/PythonService.php');
        $this->assertStringNotContainsString("implode(' '", $src);
    }
}
