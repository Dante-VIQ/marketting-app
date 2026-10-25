<?php

namespace App\Services\Competitors\Discovery;

/**
 * Pure text logic for discovery: which words describe the brand, how many of them a candidate's site shares,
 * and what kind of competitor it looks like. No network, no database.
 */
class Relevance
{
    private const STOP = ['this', 'that', 'with', 'from', 'your', 'have', 'will', 'they', 'their', 'there', 'what', 'when', 'where', 'which', 'about',
        'into', 'more', 'most', 'some', 'such', 'than', 'then', 'them', 'also', 'only', 'other', 'over', 'very', 'been', 'being', 'were', 'does',
        'platform', 'content', 'website', 'site', 'brand', 'business', 'company', 'services', 'service', 'revenue', 'affiliate', 'commission',
        'commissions', 'partner', 'partners', 'network', 'networks', 'media', 'never', 'always', 'help', 'helps', 'like', 'just', 'make', 'made',
        'based', 'using', 'used', 'through', 'across', 'beyond', 'authentic', 'topic', 'topics', 'write', 'about', 'every', 'first', 'must', 'should'];

    /** Distinct words (4+ letters), most frequent first. $exclude drops the brand's own name words. */
    public static function terms(array $texts, array $exclude = [], int $limit = 40): array
    {
        $skip = array_flip(array_merge(self::STOP, array_map('strtolower', $exclude)));
        $count = [];
        foreach ($texts as $t) {
            if (!is_string($t) || $t === '') {
                continue;
            }
            preg_match_all('/\p{L}{4,}/u', mb_strtolower($t), $m);
            foreach ($m[0] as $w) {
                if (!isset($skip[$w])) {
                    $count[$w] = ($count[$w] ?? 0) + 1;
                }
            }
        }
        arsort($count);

        return array_slice(array_keys($count), 0, $limit);
    }

    /**
     * How much of the brand's vocabulary appears in the candidate's own words.
     * @return array{score:int,shared:string[],of:int} score = % of the brand's top words (max 15) found on their site.
     */
    public static function overlap(array $brandTerms, string $candidateText): array
    {
        $top = array_slice($brandTerms, 0, 15);
        if (!$top) {
            return ['score' => 0, 'shared' => [], 'of' => 0];
        }
        $have = array_flip(self::terms([$candidateText], [], 400));
        $shared = array_values(array_filter($top, fn ($t) => isset($have[$t])));

        return ['score' => (int) round(100 * count($shared) / count($top)), 'shared' => $shared, 'of' => count($top)];
    }

    public static function isBigPlatform(string $domain, array $list): bool
    {
        foreach ($list as $big) {
            if ($domain === $big || str_ends_with($domain, '.' . $big)) {
                return true;
            }
        }

        return false;
    }

    /** bookings = sells trips, content = publishes to attract readers, both, or unclear. */
    public static function classify(string $text, bool $hasFeed): string
    {
        $sells = (bool) preg_match('/\b(book now|book a|booking|tours?|packages?|itinerar\w+|get a quote|enquir\w+|inquir\w+|per person|from \$|reserve)\b/i', $text);
        $publishes = $hasFeed || (bool) preg_match('/\b(blog|travel guides?|articles?|stories|journal|read more|latest posts?)\b/i', $text);

        return match (true) {
            $sells && $publishes => 'both',
            $sells => 'bookings',
            $publishes => 'content',
            default => 'unclear',
        };
    }

    /** Domains mentioned in free text, e.g. a notes field that lists competitors. */
    public static function domainsIn(string $text): array
    {
        preg_match_all('/\b((?:[a-z0-9][a-z0-9-]*\.)+[a-z]{2,24})\b/i', $text, $m);
        $out = [];
        foreach ($m[1] as $d) {
            $d = strtolower(preg_replace('/^www\./i', '', $d));
            if (preg_match('/\.(php|html?|js|css|png|jpe?g|gif|pdf)$/', $d)) {
                continue;
            }
            $out[$d] = true;
        }

        return array_keys($out);
    }
}
