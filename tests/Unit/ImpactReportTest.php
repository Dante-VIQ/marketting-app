<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Services\Impact\ImpactReport;
use Illuminate\Database\Capsule\Manager as DB;
use Tests\Unit\Support\MetaTestCase;

class ImpactReportTest extends MetaTestCase
{
    private const TODAY = '2026-10-25';

    protected function setUp(): void
    {
        parent::setUp();
        $s = DB::schema();
        $s->create('analytics_snapshots', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->date('date'); $t->string('source'); $t->string('metric'); $t->string('dimension')->nullable(); $t->decimal('value', 15, 2)->default(0); $t->text('metadata')->nullable(); $t->timestamps(); });
        $s->create('ai_actions', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('status')->nullable(); $t->timestamp('executed_at')->nullable(); $t->timestamps(); });
        $s->create('leads', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->timestamps(); });
        $s->create('action_verifications', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->unsignedBigInteger('action_id'); $t->string('phase'); $t->boolean('was_successful')->default(false); $t->boolean('rollback_triggered')->default(false); $t->timestamp('verified_at')->nullable(); $t->timestamps(); });
        $s->create('revenue_entries', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->date('month'); $t->string('source'); $t->decimal('amount', 12, 2); $t->string('currency', 3); $t->string('note')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps(); });
    }

    private function days(string $from, int $n, float $sessions, float $conv = 0, ?int $skipEvery = null): void
    {
        for ($i = 0; $i < $n; $i++) {
            if ($skipEvery && $i % $skipEvery === 0) { continue; }
            $d = date('Y-m-d', strtotime("$from +$i days"));
            foreach (['sessions' => $sessions, 'visitors' => $sessions * 0.8, 'conversions' => $conv] as $m => $v) {
                DB::table('analytics_snapshots')->insert(['brand_id' => 1, 'date' => $d, 'source' => 'ga4', 'metric' => $m, 'dimension' => null, 'value' => $v]);
            }
        }
    }

    private function started(string $at): void
    {
        DB::table('ai_actions')->insert(['brand_id' => 1, 'status' => 'executed', 'executed_at' => $at . ' 10:00:00']);
    }

    public function test_windows_are_equal_length_and_exclude_today(): void
    {
        $w = ImpactReport::windows('2026-10-01', self::TODAY);   // 24 full days: Oct 1..24
        $this->assertSame(24, $w['len']);
        $this->assertSame(['2026-09-07', '2026-09-30', '2026-10-01', '2026-10-24'], [$w['before_from'], $w['before_to'], $w['after_from'], $w['after_to']]);
        $this->assertSame(ImpactReport::MAX_DAYS, ImpactReport::windows('2026-01-01', self::TODAY)['len']);   // capped
        $this->assertSame(0, ImpactReport::windows(self::TODAY, self::TODAY)['len']);
    }

    public function test_change_is_null_when_there_was_nothing_before(): void
    {
        $this->assertSame(50.0, ImpactReport::change(100, 150));
        $this->assertSame(-20.0, ImpactReport::change(100, 80));
        $this->assertNull(ImpactReport::change(0, 40));
    }

    public function test_not_started_until_the_system_actually_did_something(): void
    {
        $r = (new ImpactReport())->build(1, self::TODAY);
        $this->assertSame('not_started', $r['state']);
        $this->assertArrayNotHasKey('rows', $r);
    }

    public function test_first_approved_post_also_counts_as_start(): void
    {
        ContentIdea::create(['brand_id' => 1, 'title' => 't', 'status' => 'posted', 'approved_at' => '2026-10-10 09:00:00']);
        $this->assertSame('2026-10-10', ImpactReport::startDate(1));
    }

    public function test_too_early_says_how_long_to_wait(): void
    {
        $this->started('2026-10-20');   // 5 full days
        $r = (new ImpactReport())->build(1, self::TODAY);
        $this->assertSame('too_early', $r['state']);
        $this->assertSame(9, $r['days_left']);
    }

    public function test_missing_history_is_reported_not_guessed(): void
    {
        $this->started('2026-10-01');
        $this->days('2026-10-01', 24, 100);   // after-window data exists, nothing before
        $r = (new ImpactReport())->build(1, self::TODAY);
        $this->assertSame('no_baseline', $r['state']);
        $this->assertSame(0, $r['coverage']['before_days']);
        $this->assertStringContainsString('analytics:backfill-ga4', $r['note']);
    }

    public function test_compares_equal_periods_with_real_numbers(): void
    {
        $this->started('2026-10-01');
        $this->days('2026-09-07', 24, 100, 2);    // before: 24 days
        $this->days('2026-10-01', 24, 150, 3);    // after: 24 days
        DB::table('leads')->insert([['brand_id' => 1, 'created_at' => '2026-09-15 10:00:00'], ['brand_id' => 1, 'created_at' => '2026-10-05 10:00:00'], ['brand_id' => 1, 'created_at' => '2026-10-06 10:00:00'], ['brand_id' => 2, 'created_at' => '2026-10-06 10:00:00']]);

        $r = (new ImpactReport())->build(1, self::TODAY);
        $rows = array_column($r['rows'], null, 'key');

        $this->assertSame('ok', $r['state']);
        $this->assertEquals([2400, 3600, 50.0], [$rows['sessions']['before'], $rows['sessions']['after'], $rows['sessions']['change_pct']]);
        $this->assertEquals([48, 72], [$rows['conversions']['before'], $rows['conversions']['after']]);
        $this->assertEquals([1, 2, 100.0], [$rows['leads']['before'], $rows['leads']['after'], $rows['leads']['change_pct']]);   // other brand's lead ignored
        $this->assertStringContainsString('not what caused it', $r['caveat']);
        $this->assertCount(12, $r['weekly']);
    }

    public function test_gaps_in_traffic_data_below_coverage_block_the_comparison(): void
    {
        $this->started('2026-10-01');
        $this->days('2026-09-07', 24, 100, 0, 2);   // every other day missing: 12 of 24
        $this->days('2026-10-01', 24, 150);
        $this->assertSame('no_baseline', (new ImpactReport())->build(1, self::TODAY)['state']);
    }

    public function test_actions_count_only_what_was_checked(): void
    {
        DB::table('ai_actions')->insert([['brand_id' => 1, 'executed_at' => '2026-10-01 10:00:00'], ['brand_id' => 1, 'executed_at' => '2026-10-02 10:00:00'], ['brand_id' => 1, 'executed_at' => '2026-10-03 10:00:00'], ['brand_id' => 1, 'executed_at' => null]]);
        DB::table('action_verifications')->insert([
            ['brand_id' => 1, 'action_id' => 1, 'phase' => 'day_1', 'was_successful' => 1, 'rollback_triggered' => 0],
            ['brand_id' => 1, 'action_id' => 2, 'phase' => 'day_1', 'was_successful' => 0, 'rollback_triggered' => 1],
            ['brand_id' => 1, 'action_id' => 3, 'phase' => 'hour_1', 'was_successful' => 1, 'rollback_triggered' => 0],   // not a day-1 check
        ]);
        $a = (new ImpactReport())->actions(1);
        $this->assertSame([3, 2, 1, 1, 1, 1], [$a['executed'], $a['checked'], $a['improved'], $a['not_improved'], $a['rolled_back'], $a['not_checked_yet']]);
    }

    public function test_revenue_is_per_currency_and_compared_only_with_two_months_each_side(): void
    {
        $add = fn ($m, $amt, $c = 'USD') => DB::table('revenue_entries')->insert(['brand_id' => 1, 'month' => $m . '-01', 'source' => 'GYG', 'amount' => $amt, 'currency' => $c]);
        $add('2026-08', 10); $add('2026-09', 20); $add('2026-10', 30);
        $r = (new ImpactReport())->revenue(1, '2026-10-01');
        $this->assertNull($r['compare']);   // only one month after the start month: not enough

        $add('2026-11', 50);
        $add('2026-10', 5, 'KES');
        $r = (new ImpactReport())->revenue(1, '2026-10-01');
        $this->assertEquals(15.0, $r['compare']['USD']['before_avg']);
        $this->assertEquals(40.0, $r['compare']['USD']['after_avg']);
        $this->assertArrayNotHasKey('KES', $r['compare']);   // never mixed with USD, and too little data
        $this->assertSame(['USD' => 30.0, 'KES' => 5.0], $r['by_month']['2026-10']);
    }
}
