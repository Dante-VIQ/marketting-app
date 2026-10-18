<?php

namespace App\Services\Personas;

/**
 * How far a persona can be trusted. A persona with no evidence is a hypothesis,
 * and says so. Direct customer research (interviews, surveys, real conversations)
 * counts for more than inferred signals, in line with current persona guidance:
 * ground personas in what customers actually said, then validate and refresh.
 */
class PersonaConfidence
{
    private const DIRECT = ['interview', 'survey', 'support'];
    public const STALE_DAYS = 180;

    /** @param array<int,array{kind:string,observed_on:?string}> $evidence */
    public static function assess(array $evidence, ?\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $direct = 0;
        $inferred = 0;
        $latest = null;
        foreach ($evidence as $e) {
            in_array($e['kind'], self::DIRECT, true) ? $direct++ : $inferred++;
            if (!empty($e['observed_on'])) {
                $d = new \DateTimeImmutable($e['observed_on']);
                if (!$latest || $d > $latest) { $latest = $d; }
            }
        }

        $level = match (true) {
            $direct >= 10 => 'validated',
            $direct >= 3 || ($direct >= 1 && $inferred >= 2) => 'emerging',
            default => 'hypothesis',
        };

        $stale = $latest && $level !== 'hypothesis' && $latest->diff($now)->days > self::STALE_DAYS;

        $next = match ($level) {
            'hypothesis' => 'Add 3 direct sources (customer interviews, survey answers or real sales chats) to reach "emerging".',
            'emerging' => 'Reach 10 direct sources to mark this persona as validated.',
            default => 'Validated. Re-check it with a few new conversations every quarter.',
        };

        return ['level' => $level, 'direct' => $direct, 'inferred' => $inferred,
            'last_evidence' => $latest?->format('Y-m-d'), 'stale' => (bool) $stale, 'next_step' => $next];
    }
}
