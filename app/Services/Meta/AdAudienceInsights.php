<?php

namespace App\Services\Meta;

use Illuminate\Support\Collection;

/**
 * Who the ads reach and who responds, from the stored breakdown rows. Pure.
 * Small samples are not ranked: a click rate on 40 impressions says nothing.
 */
class AdAudienceInsights
{
    public const MIN_TOTAL_IMPRESSIONS = 1000;
    public const MIN_GROUP_IMPRESSIONS = 500;

    /** @param Collection|array $rows AdInsightRow-like arrays/objects @return array{state:string,currency:?string,total:?array,groups:array} */
    public static function build($rows): array
    {
        $rows = collect($rows)->map(fn ($r) => is_array($r) ? $r : (array) (method_exists($r, 'toArray') ? $r->toArray() : $r));
        if ($rows->isEmpty()) {
            return ['state' => 'no_data', 'currency' => null, 'total' => null, 'groups' => []];
        }
        $totalRow = $rows->firstWhere('breakdown', 'total');
        $total = $totalRow ? self::metrics($totalRow, null) : null;
        if (!$total || ($total['impressions'] ?? 0) < self::MIN_TOTAL_IMPRESSIONS) {
            return ['state' => 'too_early', 'currency' => $totalRow['currency'] ?? null, 'total' => $total, 'groups' => []];
        }

        $groups = [];
        foreach (['age_gender', 'country', 'placement'] as $key) {
            $set = $rows->where('breakdown', $key)->values();
            if ($set->isEmpty()) {
                continue;
            }
            $spendSum = $set->sum(fn ($r) => (float) ($r['spend'] ?? 0));
            $list = $set->map(fn ($r) => self::metrics($r, $spendSum) + ['name' => self::name($key, $r)])
                ->sortByDesc(fn ($m) => $m['impressions'] ?? 0)->values();
            $eligible = $list->filter(fn ($m) => ($m['impressions'] ?? 0) >= self::MIN_GROUP_IMPRESSIONS && $m['ctr'] !== null);
            $groups[$key] = [
                'rows' => $list->all(),
                'best_ctr' => $eligible->count() >= 2 ? $eligible->sortByDesc('ctr')->first()['name'] : null,
            ];
        }

        return ['state' => 'ok', 'currency' => $totalRow['currency'] ?? null, 'total' => $total, 'groups' => $groups];
    }

    /** Small version for the agent: state, totals and the top three groups per cut. Nothing the agent cannot act on. */
    public static function compact(array $built): array
    {
        $groups = [];
        foreach ($built['groups'] as $key => $g) {
            $groups[$key] = ['best_ctr' => $g['best_ctr'], 'top' => array_map(
                fn ($r) => array_intersect_key($r, array_flip(['name', 'impressions', 'clicks', 'ctr', 'cpc', 'spend_share'])), array_slice($g['rows'], 0, 3))];
        }

        return ['state' => $built['state'], 'currency' => $built['currency'], 'total' => $built['total'], 'groups' => $groups];
    }

    private static function metrics(array $r, ?float $spendSum): array
    {
        $imp = $r['impressions'] ?? null;
        $clicks = $r['clicks'] ?? null;
        $spend = isset($r['spend']) ? (float) $r['spend'] : null;

        return [
            'impressions' => $imp, 'reach' => $r['reach'] ?? null, 'clicks' => $clicks, 'spend' => $spend, 'results' => $r['results'] ?? null,
            'ctr' => ($imp && $clicks !== null) ? round($clicks / $imp * 100, 2) : null,
            'cpc' => ($clicks && $spend !== null) ? round($spend / $clicks, 2) : null,
            'spend_share' => ($spendSum && $spend !== null) ? round($spend / $spendSum * 100, 1) : null,
        ];
    }

    private static function name(string $key, array $r): string
    {
        return match ($key) {
            'age_gender' => trim(($r['dim1'] ?? '?') . ' · ' . ($r['dim2'] ?? '?')),
            'placement' => trim(($r['dim1'] ?? '?') . ($r['dim2'] ? ' / ' . $r['dim2'] : '')),
            default => (string) ($r['dim1'] ?? '?'),
        };
    }
}
