<?php

namespace App\Services\Competitors;

use App\Models\BlogPost;
use App\Models\Competitor;
use App\Models\KeywordRanking;

/** Reads stored competitor snapshots and compares them with our own content. */
class CompetitorInsights
{
    /**
     * @return array{tracked:int,scanned:int,competitors:array,topic_gaps:array,as_of:?string}
     */
    public function summary(int $brandId): array
    {
        $competitors = Competitor::where('brand_id', $brandId)->where('is_active', true)
            ->with('latestSnapshot')->orderBy('name')->get();

        $rows = [];
        $termMaps = [];
        $asOf = null;
        foreach ($competitors as $c) {
            $s = $c->latestSnapshot;
            $ok = $s && $s->status === 'ok';
            if ($ok && $s->top_terms) {
                $termMaps[] = $s->top_terms;
            }
            if ($s && (!$asOf || $s->captured_on->toDateString() > $asOf)) {
                $asOf = $s->captured_on->toDateString();
            }
            $rows[] = [
                'id'                => $c->id,
                'name'              => $c->name,
                'domain'            => $c->domain,
                'scanned_on'        => $s?->captured_on->toDateString(),
                'status'            => $s ? $s->status : 'never_scanned',
                'error'             => $s?->error,
                'homepage_title'    => $s?->homepage_title,
                'sitemap_url_count' => $s?->sitemap_url_count,
                'posts_last_30d'    => $s?->posts_last_30d,
                'cadence_basis'     => $s?->cadence_basis,
                'latest_post_at'    => $s?->latest_post_at?->toDateString(),
                'top_terms'         => $ok ? array_slice($s->top_terms ?? [], 0, 8, true) : [],
                'recent_posts'      => $ok ? array_slice($s->recent_posts ?? [], 0, 3) : [],
            ];
        }

        return [
            'tracked'     => count($rows),
            'scanned'     => count(array_filter($rows, fn ($r) => $r['status'] === 'ok')),
            'competitors' => $rows,
            'topic_gaps'  => $termMaps ? CompetitorParser::topicGaps($termMaps, $this->ownTexts($brandId)) : [],
            'as_of'       => $asOf,
        ];
    }

    /** Our post titles and tracked keywords: what we already cover. */
    private function ownTexts(int $brandId): array
    {
        $titles = BlogPost::where('brand_id', $brandId)->pluck('title')->all();
        $latest = KeywordRanking::where('brand_id', $brandId)->max('tracked_date');
        $keywords = $latest ? KeywordRanking::where('brand_id', $brandId)->whereDate('tracked_date', \Carbon\Carbon::parse($latest)->toDateString())->pluck('keyword')->all() : [];

        return array_merge($titles, $keywords);
    }
}
