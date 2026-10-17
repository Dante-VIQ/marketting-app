<?php

namespace App\Services\Analytics;

use App\Models\AhrefsBacklink;
use App\Models\AhrefsSiteStat;
use App\Models\AnalyticsSnapshot;
use App\Models\KeywordRanking;
use App\Models\SeoIssue;
use Carbon\Carbon;

/**
 * One compact, real-data picture of a brand for the agent's specialists:
 * GA4 trends + channels, Ahrefs authority/links, ranking movers, and data freshness.
 * Missing data is reported as missing (has_data=false / null), never as zero.
 */
class AgentEvidenceService
{
    public const DROP_ALERT_POSITIONS = 5;      // lost 5+ places...
    public const DROP_ALERT_FROM_TOP = 10;      // ...from page one
    public const LOST_LINK_MIN_DR = 40;
    public const STRIKING_MIN = 4;
    public const STRIKING_MAX = 20;

    public function __construct(private AnalyticsSummaryService $analytics)
    {
    }

    public function build(int $brandId): array
    {
        $summary = $this->analytics->summary($brandId, 7);

        $evidence = [
            'brand_id'     => $brandId,
            'generated_at' => now()->toIso8601String(),
            'analytics'    => $summary + ['channels' => $this->channels($brandId, $summary)],
            'seo'          => $this->seo($brandId),
            'competitors'  => $this->competitors($brandId),
            'listening'    => $this->listening($brandId),
        ];
        $evidence['data_quality'] = $this->dataQuality($summary, $evidence['seo']);

        return $evidence;
    }

    // ---------------------------------------------------------------- analytics

    private function channels(int $brandId, array $summary): array
    {
        if (empty($summary['has_data'])) {
            return [];
        }

        $to    = Carbon::parse($summary['as_of']);
        $from  = $to->copy()->subDays($summary['window_days'] - 1);
        $pFrom = $from->copy()->subDays($summary['window_days']);
        $pTo   = $from->copy()->subDay();

        $sum = fn (Carbon $a, Carbon $b) => AnalyticsSnapshot::where('brand_id', $brandId)
            ->where('source', 'ga4')->where('metric', 'channel_sessions')->whereNotNull('dimension')
            ->whereDate('date', '>=', $a->toDateString())->whereDate('date', '<=', $b->toDateString())
            ->selectRaw('dimension, SUM(value) as total')->groupBy('dimension')
            ->pluck('total', 'dimension')->map(fn ($v) => (float) $v)->all();

        return self::channelChanges($sum($from, $to), $sum($pFrom, $pTo));
    }

    /** @return array<int, array{channel:string,sessions:float,previous:float,change_pct:?float}> */
    public static function channelChanges(array $current, array $previous): array
    {
        $rows = [];
        foreach ($current as $channel => $sessions) {
            $prev = $previous[$channel] ?? 0.0;
            $rows[] = [
                'channel'    => $channel,
                'sessions'   => round($sessions, 1),
                'previous'   => round($prev, 1),
                'change_pct' => $prev > 0 ? round((($sessions - $prev) / $prev) * 100, 1) : null,
            ];
        }
        usort($rows, fn ($a, $b) => $b['sessions'] <=> $a['sessions']);

        return $rows;
    }

    // ---------------------------------------------------------------- seo

    /** Public read of the SEO section (authority, keyword movement, backlinks) for pages and the agent. */
    public function seoSummary(int $brandId): array
    {
        return $this->seo($brandId);
    }

    private function seo(int $brandId): array
    {
        $latestStat = AhrefsSiteStat::where('brand_id', $brandId)->orderByDesc('tracked_date')->first();
        $olderStat  = $latestStat
            ? AhrefsSiteStat::where('brand_id', $brandId)
                ->whereDate('tracked_date', '<=', $latestStat->tracked_date->copy()->subDays(25)->toDateString())
                ->orderByDesc('tracked_date')->first()
            : null;

        $latestDate = KeywordRanking::where('brand_id', $brandId)->max('tracked_date');
        $keywords   = $latestDate
            ? KeywordRanking::where('brand_id', $brandId)->whereDate('tracked_date', Carbon::parse($latestDate)->toDateString())->get()
            : collect();

        $rows = $keywords->map(fn ($k) => [
            'keyword' => $k->keyword, 'position' => $k->position, 'previous_position' => $k->previous_position,
            'volume' => $k->search_volume, 'url' => $k->page_url, 'traffic' => $k->metadata['traffic'] ?? null,
        ])->all();

        $since  = Carbon::today()->subDays(30)->toDateString();
        $lost   = AhrefsBacklink::where('brand_id', $brandId)->where('is_lost', true)
            ->whereDate('last_seen_at', '>=', $since)->orderByDesc('source_domain_rating')->get();
        $gained = AhrefsBacklink::where('brand_id', $brandId)->where('is_lost', false)
            ->whereDate('first_seen_at', '>=', $since)->count();

        $issues = SeoIssue::where('brand_id', $brandId)->where('status', 'open')
            ->selectRaw('severity, COUNT(*) as n')->groupBy('severity')->pluck('n', 'severity')->all();

        return [
            'authority' => $latestStat ? [
                'as_of'             => $latestStat->tracked_date->toDateString(),
                'domain_rating'     => $latestStat->domain_rating,
                'backlinks'         => $latestStat->backlinks,
                'referring_domains' => $latestStat->referring_domains,
                'organic_keywords'  => $latestStat->organic_keywords,
                'keywords_top3'     => $latestStat->keywords_top3,
                'organic_traffic'   => $latestStat->organic_traffic,
                'change_vs_30d'     => $olderStat ? self::statChanges($latestStat->toArray(), $olderStat->toArray()) : null,
            ] : null,
            'keywords' => [
                'as_of'     => $latestDate ? Carbon::parse($latestDate)->toDateString() : null,
                'tracked'   => count($rows),
                'top'       => array_slice(self::sortByPosition($rows), 0, 10),
                'movers'    => self::movers($rows),
                'striking_distance' => self::strikingDistance($rows),
            ],
            'backlinks' => [
                'new_last_30d'  => $gained,
                'lost_last_30d' => $lost->count(),
                'lost_top'      => $lost->take(5)->map(fn ($b) => [
                    'source' => $b->source_domain, 'dr' => $b->source_domain_rating,
                    'target' => $b->target_url, 'reason' => $b->lost_reason,
                ])->all(),
            ],
            'open_issues' => $issues,
        ];
    }

    public static function statChanges(array $now, array $then): array
    {
        $out = [];
        foreach (['domain_rating', 'backlinks', 'referring_domains', 'organic_keywords', 'organic_traffic'] as $f) {
            $out[$f] = (isset($now[$f], $then[$f])) ? $now[$f] - $then[$f] : null;   // null = unknown, not 0
        }

        return $out;
    }

    public static function sortByPosition(array $rows): array
    {
        usort($rows, fn ($a, $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX));

        return $rows;
    }

    /** Biggest risers and fallers (change = previous - current; positive = up). */
    public static function movers(array $rows, int $limit = 5): array
    {
        $withChange = [];
        foreach ($rows as $r) {
            if ($r['position'] !== null && $r['previous_position'] !== null) {
                $withChange[] = $r + ['change' => $r['previous_position'] - $r['position']];
            }
        }

        $up   = array_filter($withChange, fn ($r) => $r['change'] > 0);
        $down = array_filter($withChange, fn ($r) => $r['change'] < 0);
        usort($up, fn ($a, $b) => $b['change'] <=> $a['change']);
        usort($down, fn ($a, $b) => $a['change'] <=> $b['change']);

        return ['up' => array_slice($up, 0, $limit), 'down' => array_slice($down, 0, $limit)];
    }

    /** Keywords just off page one with real search volume: cheapest wins. */
    public static function strikingDistance(array $rows, int $limit = 8): array
    {
        $hits = array_filter($rows, fn ($r) => $r['position'] !== null
            && $r['position'] >= self::STRIKING_MIN && $r['position'] <= self::STRIKING_MAX && ($r['volume'] ?? 0) > 0);
        usort($hits, fn ($a, $b) => ($b['volume'] ?? 0) <=> ($a['volume'] ?? 0));

        return array_slice(array_values($hits), 0, $limit);
    }

    /**
     * Alerts backed by real data. Returned in the agent's opportunity shape (type seo_issue).
     */
    public function seoAlerts(int $brandId): array
    {
        $seo    = $this->seo($brandId);
        $alerts = [];

        foreach ($seo['keywords']['movers']['down'] as $r) {
            if ($r['previous_position'] <= self::DROP_ALERT_FROM_TOP && -$r['change'] >= self::DROP_ALERT_POSITIONS) {
                $alerts[] = self::rankingDropAlert($r);
            }
        }

        foreach (AhrefsBacklink::where('brand_id', $brandId)->where('is_lost', true)
            ->whereDate('last_seen_at', '>=', Carbon::today()->subDays(14)->toDateString())
            ->get() as $b) {
            if ((float) $b->source_domain_rating >= self::LOST_LINK_MIN_DR) {
                $alerts[] = self::lostBacklinkAlert($b->source_domain, (float) $b->source_domain_rating, $b->target_url, $b->lost_reason);
            }
        }

        return array_slice($alerts, 0, 8);
    }

    public static function rankingDropAlert(array $r): array
    {
        $lost = -$r['change'];

        return [
            'id'          => 'rank_drop_' . md5($r['keyword']),
            'type'        => 'seo_issue',
            'severity'    => $r['previous_position'] <= 3 || $lost >= 10 ? 'high' : 'medium',
            'title'       => "Ranking drop: {$r['keyword']}",
            'description' => "\"{$r['keyword']}\" fell from position {$r['previous_position']} to {$r['position']} (-{$lost})"
                           . (($r['volume'] ?? 0) ? ", ~{$r['volume']} searches/month." : '.'),
            'source'      => 'ahrefs_rankings',
            'detectedAt'  => gmdate('Y-m-d\TH:i:s.000\Z'),
            'target_url'  => $r['url'],
            'payload'     => ['page' => $r['url'], 'issue_type' => 'ranking_drop', 'keyword' => $r['keyword'],
                              'position' => $r['position'], 'previous_position' => $r['previous_position']],
            'impact'      => 300,
            'requires_approval' => true,
        ];
    }

    public static function lostBacklinkAlert(string $domain, float $dr, ?string $target, ?string $reason): array
    {
        return [
            'id'          => 'lost_link_' . md5($domain . $target),
            'type'        => 'seo_issue',
            'severity'    => $dr >= 70 ? 'high' : 'medium',
            'title'       => "Lost backlink from {$domain}",
            'description' => "A DR {$dr} link from {$domain} to " . ($target ?: 'the site') . ' was lost' . ($reason ? " ({$reason})." : '.'),
            'source'      => 'ahrefs_backlinks',
            'detectedAt'  => gmdate('Y-m-d\TH:i:s.000\Z'),
            'target_url'  => $target,
            'payload'     => ['page' => $target, 'issue_type' => 'backlink_lost', 'source_domain' => $domain, 'dr' => $dr],
            'impact'      => 200,
            'requires_approval' => true,
        ];
    }

    // ---------------------------------------------------------------- quality

    private function dataQuality(array $summary, array $seo): array
    {
        return [
            'ga4' => [
                'has_data'   => !empty($summary['has_data']),
                'as_of'      => $summary['as_of'] ?? null,
                'stale_days' => $summary['stale_days'] ?? null,
                'days_in_window' => $summary['days_with_data'] ?? 0,
            ],
            'ahrefs' => [
                'authority_as_of' => $seo['authority']['as_of'] ?? null,
                'keywords_as_of'  => $seo['keywords']['as_of'],
                'keywords_tracked' => $seo['keywords']['tracked'],
            ],
        ];
    }
}
