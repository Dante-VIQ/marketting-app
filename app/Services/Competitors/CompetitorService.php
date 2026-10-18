<?php

namespace App\Services\Competitors;

use App\Models\Brand;
use App\Models\Competitor;
use App\Models\CompetitorKeyword;
use App\Models\KeywordRanking;
use App\Services\Ahrefs\AhrefsApi;
use App\Services\Ahrefs\AhrefsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Finds who really competes with a brand in organic search (Ahrefs), keeps a short
 * list, and works out which of their top keywords the brand does not rank for.
 * Nothing is invented: if Ahrefs fails, nothing is stored.
 */
class CompetitorService
{
    public function __construct(private ?AhrefsApi $api = null, private ?AhrefsService $ahrefs = null)
    {
        $this->api    ??= new AhrefsApi();
        $this->ahrefs ??= new AhrefsService($this->api);
    }

    public function collectForBrand(Brand $brand): array
    {
        if (!$this->ahrefs->isConfigured()) {
            return ['error' => 'Ahrefs API token not configured'];
        }
        $domain = $this->ahrefs->getTargetDomain($brand);
        if (!$domain) {
            return ['error' => 'No website URL configured for this brand.'];
        }

        $cfg     = config('services.competitors');
        $country = $brand->config['ahrefs_country'] ?? $cfg['default_country'];
        $today   = Carbon::today();

        try {
            $found = $this->api->competitors($domain, $today->toDateString(), $country, 25);
        } catch (\Throwable $e) {
            Log::error('Competitor discovery failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }

        $ignored  = Competitor::where('brand_id', $brand->id)->where('status', 'ignored')->pluck('domain')->all();
        $selected = self::selectCompetitors($found, $domain, array_merge($cfg['ignore_domains'], $ignored), (int) $cfg['max_tracked']);

        foreach ($selected as $c) {
            // updateOrCreate leaves `status`/`source` alone on existing rows
            Competitor::updateOrCreate(
                ['brand_id' => $brand->id, 'domain' => $c['domain']],
                [
                    'domain_rating' => $c['domain_rating'], 'keywords_common' => $c['keywords_common'],
                    'keywords_competitor' => $c['keywords_competitor'], 'keywords_target' => $c['keywords_target'],
                    'share' => $c['share'], 'traffic' => $c['traffic'], 'traffic_change' => $c['traffic_change'],
                    'tracked_date' => $today,
                ]
            );
        }

        $gaps = $this->collectGaps($brand, $country, $today, (int) $cfg['gap_competitors'], (int) $cfg['gap_keywords']);

        return ['collected' => ['competitors' => count($selected), 'gap_keywords' => $gaps]];
    }

    private function collectGaps(Brand $brand, string $country, Carbon $today, int $howMany, int $perCompetitor): int
    {
        $top = Competitor::where('brand_id', $brand->id)->where('status', 'tracked')
            ->whereNotNull('keywords_competitor')->orderByDesc('keywords_common')->limit($howMany)->get();

        $latest = KeywordRanking::where('brand_id', $brand->id)->max('tracked_date');
        $own    = [];
        if ($latest) {
            foreach (KeywordRanking::where('brand_id', $brand->id)->whereDate('tracked_date', $latest)->get() as $k) {
                $own[mb_strtolower($k->keyword)] = $k->position;
            }
        }

        $total = 0;
        foreach ($top as $c) {
            try {
                $rows = $this->api->competitorKeywords($c->domain, $today->toDateString(), $country, $perCompetitor);
            } catch (\Throwable $e) {
                Log::warning('Competitor keyword pull failed', ['competitor' => $c->domain, 'error' => $e->getMessage()]);
                continue;   // keep the previous set for this competitor
            }

            $gapSet = array_flip(array_column(self::gapKeywords($rows, $own), 'keyword'));

            foreach ($rows as $r) {
                if ($r['keyword'] === '') {
                    continue;
                }
                CompetitorKeyword::updateOrCreate(
                    ['competitor_id' => $c->id, 'keyword' => $r['keyword']],
                    [
                        'brand_id' => $brand->id, 'position' => $r['position'], 'volume' => $r['volume'],
                        'difficulty' => $r['difficulty'], 'url' => $r['url'], 'traffic' => $r['traffic'],
                        'is_gap' => isset($gapSet[$r['keyword']]), 'tracked_date' => $today,
                    ]
                );
            }
            // drop rows from earlier pulls that this pull no longer returned
            CompetitorKeyword::where('competitor_id', $c->id)->whereDate('tracked_date', '<', $today->toDateString())->delete();
            $total += count($gapSet);
        }

        return $total;
    }

    // ------------------------------------------------------------ pure helpers

    public static function normalizeDomain(?string $d): string
    {
        $d = mb_strtolower(trim((string) $d));
        $d = preg_replace('#^[a-z]+://#', '', $d);
        $d = explode('/', $d)[0];

        return preg_replace('/^www\./', '', $d);
    }

    public static function isNoise(?string $domain, string $own, array $ignore): bool
    {
        $d = self::normalizeDomain($domain);
        if ($d === '') {
            return true;
        }
        $own = self::normalizeDomain($own);
        if ($d === $own || str_ends_with($d, '.' . $own) || str_ends_with($own, '.' . $d)) {
            return true;
        }
        foreach ($ignore as $x) {
            $x = self::normalizeDomain($x);
            if ($x !== '' && ($d === $x || str_ends_with($d, '.' . $x))) {
                return true;
            }
        }

        return false;
    }

    /** Real rivals only, most overlapping first. */
    public static function selectCompetitors(array $rows, string $own, array $ignore, int $max): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => !self::isNoise($r['domain'] ?? null, $own, $ignore)
            && ($r['keywords_common'] ?? 0) > 0));

        usort($rows, fn ($a, $b) => [$b['keywords_common'], $b['traffic'] ?? 0] <=> [$a['keywords_common'], $a['traffic'] ?? 0]);

        return array_map(fn ($r) => ['domain' => self::normalizeDomain($r['domain'])] + $r, array_slice($rows, 0, $max));
    }

    /**
     * Competitor keywords worth targeting: they sit on page one, it has real volume,
     * and we do not already rank in the top $ownMaxPos for it.
     *
     * @param array<string,?int> $ownPositions lowercase keyword => our position
     */
    public static function gapKeywords(array $rows, array $ownPositions, int $minVolume = 50, int $maxTheirPos = 10, int $ownMaxPos = 30): array
    {
        $out = [];
        foreach ($rows as $r) {
            $mine = $ownPositions[mb_strtolower($r['keyword'])] ?? null;
            if (($r['position'] ?? 999) <= $maxTheirPos
                && ($r['volume'] ?? 0) >= $minVolume
                && ($mine === null || $mine > $ownMaxPos)) {
                $out[] = $r;
            }
        }
        usort($out, fn ($a, $b) => ($b['volume'] ?? 0) <=> ($a['volume'] ?? 0));

        return $out;
    }
}
