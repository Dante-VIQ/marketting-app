<?php

namespace App\Services\Listening;

/** Pure helpers: relevance, heuristic sentiment, parsing of the three free sources. */
class MentionAnalyzer
{
    private const POSITIVE = ['love','loved','great','excellent','amazing','recommend','recommended','best','awesome','fantastic',
        'helpful','impressed','wonderful','perfect','brilliant','reliable','smooth','easy','good','happy','nzuri','safi','asante'];
    private const NEGATIVE = ['scam','fraud','terrible','awful','worst','bad','disappointed','disappointing','rip-off','ripoff','avoid',
        'complaint','refund','broken','useless','poor','fake','lawsuit','unreliable','rude','late','overpriced','mbaya','utapeli'];

    /** Whole-word, case-insensitive match of every word of the term (so "Acme" does not match "acmetic"). */
    public static function matches(string $term, string $text): bool
    {
        $term = trim($term);
        if ($term === '' || trim($text) === '') {
            return false;
        }

        return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote(mb_strtolower($term), '/') . '(?![\p{L}\p{N}])/iu', mb_strtolower($text));
    }

    /** Rough lexicon sentiment. Defaults to neutral; null when there is no text. It is a heuristic, not a verdict. */
    public static function sentiment(string $text): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}\'-]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return null;
        }
        $score = 0;
        foreach ($words as $i => $w) {
            $negated = $i > 0 && in_array($words[$i - 1], ['not', 'no', "isn't", "wasn't", 'never'], true);
            if (in_array($w, self::POSITIVE, true)) {
                $score += $negated ? -1 : 1;
            } elseif (in_array($w, self::NEGATIVE, true)) {
                $score += $negated ? 1 : -1;
            }
        }

        return $score >= 1 ? 'positive' : ($score <= -1 ? 'negative' : 'neutral');
    }

    public static function urlHash(string $url): string
    {
        $p = parse_url(strtolower(trim($url)));
        $key = ($p['host'] ?? '') . rtrim($p['path'] ?? '', '/') . (isset($p['query']) && !str_contains($p['host'] ?? '', 'news.google') ? '' : ($p['query'] ?? ''));

        return sha1($key);
    }

    /** A thread worth joining: a question, or real engagement. */
    public static function isConversation(string $title, ?int $comments): bool
    {
        return str_contains($title, '?') || ($comments ?? 0) >= 5;
    }

    /** Google News RSS items: "Headline - Publisher". @return array<int,array> normalized mentions */
    public static function fromGoogleNews(array $feedItems): array
    {
        $out = [];
        foreach ($feedItems as $i) {
            $title = $i['title'];
            $publisher = null;
            if (preg_match('/^(.*\S)\s+-\s+([^-]{2,80})$/u', $title, $m)) {
                [$title, $publisher] = [$m[1], trim($m[2])];
            }
            if (empty($i['url'])) {
                continue;
            }
            $out[] = ['source' => 'google_news', 'title' => $title, 'url' => $i['url'], 'snippet' => null, 'author' => null,
                'publisher' => $publisher, 'published_at' => $i['date'], 'score' => null, 'comments' => null];
        }

        return $out;
    }

    /** Reddit search.json. @return array<int,array> */
    public static function fromReddit(array $json): array
    {
        $out = [];
        foreach ($json['data']['children'] ?? [] as $c) {
            $d = $c['data'] ?? [];
            if (empty($d['permalink']) || empty($d['title'])) {
                continue;
            }
            $out[] = ['source' => 'reddit', 'title' => (string) $d['title'], 'url' => 'https://www.reddit.com' . $d['permalink'],
                'snippet' => isset($d['selftext']) && $d['selftext'] !== '' ? mb_substr($d['selftext'], 0, 400) : null,
                'author' => $d['author'] ?? null, 'publisher' => isset($d['subreddit']) ? 'r/' . $d['subreddit'] : null,
                'published_at' => isset($d['created_utc']) ? gmdate('c', (int) $d['created_utc']) : null,
                'score' => $d['score'] ?? null, 'comments' => $d['num_comments'] ?? null];
        }

        return $out;
    }

    /** Hacker News Algolia. @return array<int,array> */
    public static function fromHackerNews(array $json): array
    {
        $out = [];
        foreach ($json['hits'] ?? [] as $h) {
            $title = $h['title'] ?? $h['story_title'] ?? null;
            if (!$title || empty($h['objectID'])) {
                continue;
            }
            $out[] = ['source' => 'hackernews', 'title' => $title,
                'url' => 'https://news.ycombinator.com/item?id=' . $h['objectID'],
                'snippet' => null, 'author' => $h['author'] ?? null, 'publisher' => 'Hacker News',
                'published_at' => isset($h['created_at_i']) ? gmdate('c', (int) $h['created_at_i']) : null,
                'score' => $h['points'] ?? null, 'comments' => $h['num_comments'] ?? null];
        }

        return $out;
    }
}
