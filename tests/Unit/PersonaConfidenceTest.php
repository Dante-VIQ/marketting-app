<?php

namespace Tests\Unit;

use App\Services\Personas\PersonaConfidence as C;
use PHPUnit\Framework\TestCase;

class PersonaConfidenceTest extends TestCase
{
    private function ev(string $kind, int $n, string $on = '2026-10-01'): array
    {
        return array_fill(0, $n, ['kind' => $kind, 'observed_on' => $on]);
    }

    public function test_no_evidence_is_a_hypothesis(): void
    {
        $r = C::assess([]);
        $this->assertSame('hypothesis', $r['level']);
        $this->assertFalse($r['stale']);
        $this->assertNull($r['last_evidence']);
    }

    public function test_inferred_signals_alone_never_exceed_hypothesis(): void
    {
        $this->assertSame('hypothesis', C::assess($this->ev('lead_data', 20))['level']);
    }

    public function test_three_direct_sources_is_emerging(): void
    {
        $this->assertSame('emerging', C::assess($this->ev('interview', 3))['level']);
        $this->assertSame('emerging', C::assess(array_merge($this->ev('survey', 1), $this->ev('analytics', 2)))['level']);
    }

    public function test_ten_direct_sources_is_validated(): void
    {
        $r = C::assess(array_merge($this->ev('interview', 6), $this->ev('survey', 4)));
        $this->assertSame('validated', $r['level']);
        $this->assertSame(10, $r['direct']);
    }

    public function test_old_evidence_marks_it_stale(): void
    {
        $now = new \DateTimeImmutable('2026-10-18');
        $this->assertTrue(C::assess($this->ev('interview', 3, '2026-01-01'), $now)['stale']);
        $this->assertFalse(C::assess($this->ev('interview', 3, '2026-09-01'), $now)['stale']);
    }

    public function test_a_hypothesis_is_not_flagged_stale(): void
    {
        $this->assertFalse(C::assess($this->ev('interview', 1, '2020-01-01'), new \DateTimeImmutable('2026-10-18'))['stale']);
    }
}
