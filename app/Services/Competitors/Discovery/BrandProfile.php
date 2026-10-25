<?php

namespace App\Services\Competitors\Discovery;

use App\Models\Brand;
use App\Models\KeywordRanking;
use App\Models\KnowledgeBase;
use App\Models\Persona;
use App\Services\Competitors\CompetitorParser;
use App\Services\Scanner\SiteProfileService;

/** What the system already knows about a brand, gathered so it can look for competitors without being told. */
class BrandProfile
{
    public static function build(Brand $brand): array
    {
        $kb = KnowledgeBase::where('brand_id', $brand->id)->where('is_active', true)->pluck('content', 'key')->all();
        $cap = fn ($v, $n = 700) => isset($v) && trim((string) $v) !== '' ? mb_substr(trim((string) $v), 0, $n) : null;

        $topics = [];
        try {
            $topics = array_slice(app(SiteProfileService::class)->build($brand->id)['dominant_topics'] ?? [], 0, 20);
        } catch (\Throwable $e) {
            report($e);
        }

        $keywords = [];
        try {
            $latest = KeywordRanking::where('brand_id', $brand->id)->max('tracked_date');
            if ($latest) {
                $keywords = KeywordRanking::where('brand_id', $brand->id)->whereDate('tracked_date', \Carbon\Carbon::parse($latest)->toDateString())
                    ->whereNotNull('position')->orderBy('position')->limit(15)->pluck('keyword')->all();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $personas = Persona::where('brand_id', $brand->id)->where('is_active', true)->pluck('name')->take(5)->all();

        $own = $brand->website_url ? CompetitorParser::normalizeDomain($brand->website_url) : null;
        $notes = $kb['competitors'] ?? '';
        $notedDomains = array_values(array_filter(array_map(fn ($d) => CompetitorParser::normalizeDomain($d), Relevance::domainsIn((string) $notes)), fn ($d) => $d && $d !== $own));

        $p = [
            'brand' => $brand->name,
            'own_domain' => $own,
            'about' => $cap($kb['business_description'] ?? null),
            'audience' => $cap($kb['target_audience'] ?? null),
            'services' => $cap($kb['services'] ?? null),
            'content_pillars' => $cap($kb['content_pillars'] ?? null),
            'site_topics' => $topics,
            'ranking_keywords' => $keywords,
            'personas' => $personas,
            'noted_competitors' => $notedDomains,
        ];
        $p['terms'] = Relevance::terms(
            [$p['about'], $p['audience'], $p['services'], $p['content_pillars'], implode(' ', $topics), implode(' ', $keywords)],
            preg_split('/\W+/', mb_strtolower($brand->name)) ?: []
        );

        return $p;
    }

    /** True when there is not enough real information to search on. The system should say so instead of guessing. */
    public static function isThin(array $p): bool
    {
        return !$p['about'] && !$p['content_pillars'] && !$p['site_topics'] && !$p['ranking_keywords'];
    }
}
