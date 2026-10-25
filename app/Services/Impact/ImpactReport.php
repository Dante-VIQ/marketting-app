<?php

namespace App\Services\Impact;

use App\Models\ActionVerification;
use App\Models\AiAction;
use App\Models\AnalyticsSnapshot;
use App\Models\ContentIdea;
use App\Models\Lead;
use App\Models\RevenueEntry;
use App\Services\ContentPlan\SocialSiteResults;
use Carbon\Carbon;

/**
 * Is the system helping? Compares the same number of days before and after it started doing real work.
 * This is correlation, not proof: seasons, ads and luck move numbers too. It says "not enough data" instead of guessing.
 */
class ImpactReport
{
    public const MIN_DAYS = 14;
    public const MAX_DAYS = 56;
    public const COVERAGE = 0.7;   // share of days in each window that must have data

    public const CAVEAT = 'This compares two periods. It shows what changed, not what caused it: seasons, ads and chance also move traffic.';

    /** The first day the system did real work: its first carried-out action or first human-approved post. Null = not started. */
    public static function startDate(int $brandId): ?string
    {
        $a = AiAction::where('brand_id', $brandId)->whereNotNull('executed_at')->min('executed_at');
        $p = ContentIdea::where('brand_id', $brandId)->whereNotNull('approved_at')->min('approved_at');
        $dates = array_filter([$a, $p]);

        return $dates ? Carbon::parse(min($dates))->toDateString() : null;
    }

    /**
     * Equal-length windows around the start date. The "after" window ends yesterday (today is incomplete) or after MAX_DAYS.
     * @return array{len:int,before_from:string,before_to:string,after_from:string,after_to:string}
     */
    public static function windows(string $start, string $today): array
    {
        $s = Carbon::parse($start)->startOfDay();
        $yesterday = Carbon::parse($today)->startOfDay()->subDay();
        $days = $s->gt($yesterday) ? 0 : (int) $s->diffInDays($yesterday) + 1;
        $len = min($days, self::MAX_DAYS);

        return ['len' => $len,
            'before_from' => $s->copy()->subDays($len)->toDateString(), 'before_to' => $s->copy()->subDay()->toDateString(),
            'after_from' => $s->toDateString(), 'after_to' => $len ? $s->copy()->addDays($len - 1)->toDateString() : $s->toDateString()];
    }

    /** % change, or null when the earlier number is zero (a percentage of nothing means nothing). */
    public static function change(float $before, float $after): ?float
    {
        return $before > 0 ? round(($after - $before) / $before * 100, 1) : null;
    }

    /** @return array{state:string,...} */
    public function build(int $brandId, ?string $today = null): array
    {
        $today ??= now()->toDateString();
        $start = self::startDate($brandId);
        $out = ['start' => $start, 'caveat' => self::CAVEAT, 'actions' => $this->actions($brandId), 'social' => SocialSiteResults::summary($brandId),
            'revenue' => $this->revenue($brandId, $start)];

        if (!$start) {
            return ['state' => 'not_started', 'note' => 'The system has not carried out an action or published an approved post yet, so there is nothing to compare.'] + $out;
        }

        $w = self::windows($start, $today);
        $out['windows'] = $w;
        if ($w['len'] < self::MIN_DAYS) {
            return ['state' => 'too_early', 'days_left' => self::MIN_DAYS - $w['len'],
                'note' => 'Only ' . $w['len'] . ' full days since the system started. Comparing needs ' . self::MIN_DAYS . ', so check back in ' . (self::MIN_DAYS - $w['len']) . ' days.'] + $out;
        }

        $before = $this->totals($brandId, $w['before_from'], $w['before_to']);
        $after = $this->totals($brandId, $w['after_from'], $w['after_to']);
        $need = (int) ceil($w['len'] * self::COVERAGE);
        $out['coverage'] = ['before_days' => $before['days'], 'after_days' => $after['days'], 'needed' => $need];
        if ($before['days'] < $need || $after['days'] < $need) {
            return ['state' => 'no_baseline',
                'note' => 'Traffic history is missing for part of the comparison (' . $before['days'] . ' of ' . $w['len'] . ' days before, ' . $after['days'] . ' after). Without it the comparison would be a guess. Run php artisan analytics:backfill-ga4 to load older days.'] + $out;
        }

        $rows = [];
        foreach (['sessions' => 'Visits', 'visitors' => 'Visitors', 'conversions' => 'Key events (sign-ups etc.)', 'leads' => 'Leads recorded'] as $k => $label) {
            $rows[] = ['key' => $k, 'label' => $label, 'before' => $before[$k], 'after' => $after[$k], 'change_pct' => self::change($before[$k], $after[$k])];
        }

        return ['state' => 'ok', 'rows' => $rows, 'weekly' => $this->weekly($brandId, $start, $today)] + $out;
    }

    /** @return array{sessions:float,visitors:float,conversions:float,leads:float,days:int} */
    private function totals(int $brandId, string $from, string $to): array
    {
        $sum = fn (string $m) => (float) AnalyticsSnapshot::where('brand_id', $brandId)->where('source', 'ga4')->where('metric', $m)->whereNull('dimension')
            ->whereBetween('date', [$from, $to])->sum('value');
        $days = AnalyticsSnapshot::where('brand_id', $brandId)->where('source', 'ga4')->where('metric', 'sessions')->whereNull('dimension')
            ->whereBetween('date', [$from, $to])->distinct()->count('date');

        return ['sessions' => $sum('sessions'), 'visitors' => $sum('visitors'), 'conversions' => $sum('conversions'),
            'leads' => (float) Lead::where('brand_id', $brandId)->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->count(), 'days' => $days];
    }

    /** Visits and key events for the last 12 complete weeks, with the week the system started marked. */
    private function weekly(int $brandId, string $start, string $today): array
    {
        $rows = [];
        $end = Carbon::parse($today)->startOfWeek()->subDay();   // last Sunday: only complete weeks
        for ($i = 11; $i >= 0; $i--) {
            $to = $end->copy()->subWeeks($i);
            $from = $to->copy()->subDays(6);
            $q = fn (string $m) => AnalyticsSnapshot::where('brand_id', $brandId)->where('source', 'ga4')->where('metric', $m)->whereNull('dimension')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            $n = (clone $q('sessions'))->distinct()->count('date');
            $rows[] = ['week' => $from->toDateString(), 'sessions' => $n === 7 ? (int) $q('sessions')->sum('value') : null,
                'conversions' => $n === 7 ? (int) $q('conversions')->sum('value') : null,
                'started' => $start >= $from->toDateString() && $start <= $to->toDateString()];
        }

        return $rows;
    }

    /** What happened to the actions the system carried out, by the checks made afterwards. Unverified is not counted as success. */
    public function actions(int $brandId): array
    {
        $executed = AiAction::where('brand_id', $brandId)->whereNotNull('executed_at')->count();
        $day1 = ActionVerification::where('brand_id', $brandId)->where('phase', 'day_1')->get(['action_id', 'was_successful', 'rollback_triggered']);
        $checked = $day1->unique('action_id');

        return ['executed' => $executed, 'checked' => $checked->count(), 'improved' => $checked->where('was_successful', true)->count(),
            'not_improved' => $checked->where('was_successful', false)->count(), 'rolled_back' => $checked->where('rollback_triggered', true)->count(),
            'not_checked_yet' => max(0, $executed - $checked->count())];
    }

    /**
     * Money logged by a person. Each currency is kept separate (no conversion). Before/after averages appear only with
     * at least two logged months on each side of the start month.
     */
    public function revenue(int $brandId, ?string $start): array
    {
        $rows = RevenueEntry::where('brand_id', $brandId)->orderByDesc('month')->get();
        $byMonth = [];
        foreach ($rows as $r) {
            $m = Carbon::parse($r->month)->format('Y-m');
            $byMonth[$m][$r->currency] = ($byMonth[$m][$r->currency] ?? 0) + (float) $r->amount;
        }
        krsort($byMonth);

        $compare = null;
        if ($start && $byMonth) {
            $startMonth = Carbon::parse($start)->format('Y-m');
            $cur = [];
            foreach ($byMonth as $m => $cs) {
                foreach ($cs as $c => $amt) {
                    $cur[$c][$m < $startMonth ? 'before' : 'after'][] = $amt;
                }
            }
            foreach ($cur as $c => $sides) {
                if (count($sides['before'] ?? []) >= 2 && count($sides['after'] ?? []) >= 2) {
                    $b = array_sum($sides['before']) / count($sides['before']);
                    $a = array_sum($sides['after']) / count($sides['after']);
                    $compare[$c] = ['before_avg' => round($b, 2), 'after_avg' => round($a, 2), 'change_pct' => self::change($b, $a),
                        'months_before' => count($sides['before']), 'months_after' => count($sides['after'])];
                }
            }
        }

        return ['entries' => $rows, 'by_month' => $byMonth, 'compare' => $compare];
    }
}
