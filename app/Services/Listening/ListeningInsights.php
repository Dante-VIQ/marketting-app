<?php

namespace App\Services\Listening;

use App\Models\KeywordRanking;
use App\Models\ListeningQuery;
use App\Models\Mention;

/**
 * Turns stored mentions into a picture. Designed for the "nothing found yet" case:
 * zero mentions is reported as a starting point with next steps, never as an error or a drop.
 */
class ListeningInsights
{
    public const STATES = ['no_queries', 'never_checked', 'no_mentions_yet', 'active'];

    public function summary(int $brandId): array
    {
        $queries = ListeningQuery::where('brand_id', $brandId)->where('is_active', true)->orderBy('kind')->orderBy('term')->get();
        $lastChecked = $queries->max('last_checked_at');

        $base = [
            'state'        => 'no_queries',
            'queries'      => $queries->map(fn ($q) => ['id' => $q->id, 'term' => $q->term, 'kind' => $q->kind,
                'last_checked_at' => $q->last_checked_at?->toDateTimeString()])->all(),
            'checked_on'   => $lastChecked?->toDateString(),
            'sources'      => $this->sourceHealth($queries),
            'brand'        => ['last_7d' => 0, 'previous_7d' => 0, 'change_pct' => null, 'total_30d' => 0, 'sentiment_30d' => []],
            'share_of_voice' => [],
            'recent'       => [],
            'conversations' => [],
            'suggestions'  => [],
        ];

        if ($queries->isEmpty()) {
            return $base;
        }
        if (!$lastChecked) {
            return ['state' => 'never_checked', 'suggestions' => $this->suggestions($brandId, $queries)] + $base;
        }

        $mentions = Mention::where('brand_id', $brandId)->where('is_dismissed', false)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '>=', now()->subDays(30)))
            ->with('listeningQuery:id,term,kind')->get();

        $brandM = $mentions->filter(fn ($m) => $m->listeningQuery?->kind === 'brand');
        $now = now();
        $in = fn ($c, $from, $to) => $c->filter(fn ($m) => $m->published_at && $m->published_at->gte($from) && $m->published_at->lt($to))->count();
        $last7 = $in($brandM, $now->copy()->subDays(7), $now->copy()->addDay());
        $prev7 = $in($brandM, $now->copy()->subDays(14), $now->copy()->subDays(7));

        $base['brand'] = [
            'last_7d'       => $last7,
            'previous_7d'   => $prev7,
            'change_pct'    => $prev7 > 0 ? round((($last7 - $prev7) / $prev7) * 100, 1) : null,   // no baseline = no trend
            'total_30d'     => $brandM->count(),
            'sentiment_30d' => $brandM->whereNotNull('sentiment')->countBy('sentiment')->all(),
        ];

        $base['share_of_voice'] = $mentions->filter(fn ($m) => in_array($m->listeningQuery?->kind, ['brand', 'competitor'], true))
            ->groupBy(fn ($m) => $m->listeningQuery->term)
            ->map(fn ($g, $term) => ['term' => $term, 'kind' => $g->first()->listeningQuery->kind, 'mentions' => $g->count()])
            ->sortByDesc('mentions')->values()->all();

        $base['recent'] = $brandM->sortByDesc('published_at')->take(15)->map(fn ($m) => $this->row($m))->values()->all();

        $base['conversations'] = $mentions->filter(fn ($m) => $m->listeningQuery?->kind === 'topic' && MentionAnalyzer::isConversation($m->title, $m->comments))
            ->sortByDesc(fn ($m) => ($m->comments ?? 0) + ($m->score ?? 0))->take(8)->map(fn ($m) => $this->row($m))->values()->all();

        $base['state'] = $brandM->isEmpty() && $mentions->isEmpty() ? 'no_mentions_yet' : 'active';
        $base['suggestions'] = $base['state'] === 'no_mentions_yet' || $brandM->isEmpty() ? $this->suggestions($brandId, $queries) : [];

        return $base;
    }

    private function row($m): array
    {
        return ['id' => $m->id, 'title' => $m->title, 'url' => $m->url, 'source' => $m->source, 'publisher' => $m->publisher,
            'date' => $m->published_at?->toDateString(), 'sentiment' => $m->sentiment, 'comments' => $m->comments, 'score' => $m->score,
            'term' => $m->listeningQuery?->term];
    }

    /** Worst status per source across queries, so one blocked source is visible instead of hidden. */
    private function sourceHealth($queries): array
    {
        $rank = ['ok' => 0, 'error' => 1, 'blocked' => 2];
        $out = [];
        foreach ($queries as $q) {
            foreach ($q->source_status ?? [] as $src => $st) {
                if (!isset($out[$src]) || $rank[$st] > $rank[$out[$src]]) {
                    $out[$src] = $st;
                }
            }
        }

        return $out;
    }

    /** Where to start when nothing has been found: concrete, brand-specific next steps. */
    private function suggestions(int $brandId, $queries): array
    {
        $tracked = $queries->pluck('term')->map(fn ($t) => mb_strtolower($t))->all();
        $latest = KeywordRanking::where('brand_id', $brandId)->max('tracked_date');
        $kw = $latest ? KeywordRanking::where('brand_id', $brandId)->whereDate('tracked_date', \Carbon\Carbon::parse($latest)->toDateString())->whereNotNull('search_volume')
            ->orderByDesc('search_volume')->limit(8)->pluck('keyword')->all() : [];
        $topics = array_values(array_filter($kw, fn ($k) => !in_array(mb_strtolower($k), $tracked, true)));

        return [
            'topics' => array_slice($topics, 0, 5),   // one-click "listen for this topic"
            'tips'   => [
                'Add alternative spellings, your domain, or a product or founder name as extra brand terms.',
                'Listen for topics your customers talk about; conversations there are chances to join in.',
                'Add competitors on the Competitors page: their mentions appear here for comparison.',
            ],
        ];
    }
}
