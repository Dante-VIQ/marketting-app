<?php

namespace App\Services\Listening;

use App\Models\Brand;
use App\Models\Competitor;
use App\Models\ListeningQuery;
use App\Models\Mention;
use App\Services\Competitors\CompetitorParser;
use Illuminate\Http\Client\Factory as Http;

/**
 * Free social listening: Google News RSS, Reddit search and Hacker News (Algolia).
 * No keys. A brand with no mentions is a normal state: the run still records that it checked.
 */
class ListeningCollector
{
    public const SOURCES = ['google_news', 'reddit', 'hackernews'];
    private const MAX_QUERIES = 20;

    private Http $http;
    /** @var callable */
    private $pause;

    public function __construct(?Http $http = null, ?callable $pause = null)
    {
        $this->http = $http ?? app(Http::class);
        $this->pause = $pause ?? fn () => usleep(800_000);
    }

    /** Make sure a brand always has something to listen for, even before anyone configures anything. */
    public function ensureDefaults(Brand $brand): void
    {
        $terms = array_filter([
            trim((string) $brand->name),
            $brand->website_url ? CompetitorParser::normalizeDomain($brand->website_url) : null,
        ]);
        foreach ($terms as $t) {
            ListeningQuery::firstOrCreate(['brand_id' => $brand->id, 'kind' => 'brand', 'term' => mb_substr($t, 0, 120)]);
        }

        // Competitor names follow the Competitors page: new ones appear, removed ones switch off.
        $names = Competitor::where('brand_id', $brand->id)->where('is_active', true)->get()
            ->map(fn ($c) => mb_substr($c->name ?: $c->domain, 0, 120))->all();
        foreach ($names as $n) {
            ListeningQuery::updateOrCreate(['brand_id' => $brand->id, 'kind' => 'competitor', 'term' => $n], ['is_active' => true]);
        }
        ListeningQuery::where('brand_id', $brand->id)->where('kind', 'competitor')->whereNotIn('term', $names ?: [''])->update(['is_active' => false]);
    }

    /** @return array{queries:int,new_mentions:int} */
    public function collectForBrand(Brand $brand): array
    {
        $this->ensureDefaults($brand);
        $queries = ListeningQuery::where('brand_id', $brand->id)->where('is_active', true)->limit(self::MAX_QUERIES)->get();

        $new = 0;
        foreach ($queries as $q) {
            $new += $this->collectQuery($q);
        }
        Mention::where('brand_id', $brand->id)->where('published_at', '<', now()->subDays(180))->delete();

        return ['queries' => $queries->count(), 'new_mentions' => $new];
    }

    /** Check one query across all sources; returns how many NEW mentions were stored. */
    public function collectQuery(ListeningQuery $q): int
    {
        $status = [];
        $stored = 0;

        foreach (self::SOURCES as $source) {
            [$items, $status[$source]] = $this->fetch($source, $q->term);
            foreach ($items as $item) {
                if ($source !== 'google_news' && !MentionAnalyzer::matches($q->term, $item['title'] . ' ' . ($item['snippet'] ?? ''))) {
                    continue;   // reddit/HN search is fuzzy; keep only real mentions
                }
                $hash = MentionAnalyzer::urlHash($item['url']);
                $mention = Mention::firstOrCreate(
                    ['listening_query_id' => $q->id, 'url_hash' => $hash],
                    [
                        'brand_id'     => $q->brand_id,
                        'source'       => $item['source'],
                        'title'        => mb_substr($item['title'], 0, 500),
                        'url'          => mb_substr($item['url'], 0, 1000),
                        'snippet'      => $item['snippet'],
                        'author'       => $item['author'],
                        'publisher'    => $item['publisher'],
                        'published_at' => $item['published_at'] ? gmdate('Y-m-d H:i:s', strtotime($item['published_at'])) : null,
                        'score'        => $item['score'],
                        'comments'     => $item['comments'],
                        'sentiment'    => $q->kind === 'topic' ? null : MentionAnalyzer::sentiment($item['title'] . ' ' . ($item['snippet'] ?? '')),
                    ]
                );
                $stored += $mention->wasRecentlyCreated ? 1 : 0;
            }
            ($this->pause)();
        }

        $q->update(['last_checked_at' => now(), 'source_status' => $status]);

        return $stored;
    }

    /** @return array{0:array,1:string} [normalized items, status ok|blocked|error] */
    public function fetch(string $source, string $term): array
    {
        $phrase = '"' . str_replace('"', '', $term) . '"';
        $ua = ['User-Agent' => 'VumbiListening/1.0 (brand monitoring; public data only)'];

        try {
            $http = $this->http->timeout(12)->withHeaders($ua);
            switch ($source) {
                case 'google_news':
                    $r = $http->get('https://news.google.com/rss/search', ['q' => $phrase . ' when:30d', 'hl' => 'en-KE', 'gl' => 'KE', 'ceid' => 'KE:en']);
                    break;
                case 'reddit':
                    $r = $http->get('https://www.reddit.com/search.json', ['q' => $phrase, 'sort' => 'new', 't' => 'month', 'limit' => 25]);
                    break;
                default:
                    $r = $http->get('https://hn.algolia.com/api/v1/search_by_date', ['query' => $phrase, 'tags' => 'story', 'hitsPerPage' => 25,
                        'numericFilters' => 'created_at_i>' . (time() - 30 * 86400)]);
            }
        } catch (\Throwable $e) {
            return [[], 'error'];
        }

        if (in_array($r->status(), [401, 403, 429], true)) {
            return [[], 'blocked'];
        }
        if (!$r->successful()) {
            return [[], 'error'];
        }

        $items = match ($source) {
            'google_news' => MentionAnalyzer::fromGoogleNews(CompetitorParser::parseFeed($r->body())),
            'reddit'      => MentionAnalyzer::fromReddit($r->json() ?? []),
            default       => MentionAnalyzer::fromHackerNews($r->json() ?? []),
        };

        return [$items, 'ok'];
    }
}
