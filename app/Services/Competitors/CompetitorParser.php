<?php

namespace App\Services\Competitors;

/**
 * Pure, framework-free parsing and analysis for competitor scans.
 * Everything here works on strings/arrays so it can be tested without network or DB.
 */
class CompetitorParser
{
    private const STOPWORDS = [
        'the','and','for','with','your','you','our','are','this','that','from','how','what','why','when','where','who',
        'best','top','guide','tips','new','about','into','all','can','will','has','have','not','but','more','most',
        'les','des','une','pour','dans','sur','avec','que','qui','der','die','das','und','für','mit','los','las','para',
        'wa','na','ya','za','kwa','ni','katika',
    ];

    /** "https://www.Example.com/path" -> "example.com"; null when it is not a usable host. */
    public static function normalizeDomain(string $input): ?string
    {
        $input = trim(strtolower($input));
        if ($input === '') {
            return null;
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#', $input)) {
            $input = 'http://' . $input;
        }
        $host = parse_url($input, PHP_URL_HOST);
        if (!$host) {
            return null;
        }
        $host = preg_replace('/^www\./', '', $host);

        return preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) ? $host : null;
    }

    /** False for loopback, private, link-local and reserved addresses (SSRF guard). */
    public static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** @return array{type:string,entries:array<int,array{loc:string,lastmod:?string}>} type = index|urlset|invalid */
    public static function parseSitemap(string $xml): array
    {
        $doc = self::loadXml($xml);
        if (!$doc) {
            return ['type' => 'invalid', 'entries' => []];
        }

        $entries = [];
        $type = strtolower($doc->getName()) === 'sitemapindex' ? 'index' : 'urlset';
        $nodes = $type === 'index' ? $doc->xpath("//*[local-name()='sitemap']") : $doc->xpath("//*[local-name()='url']");
        foreach ($nodes ?: [] as $n) {
            $loc = trim((string) ($n->xpath("*[local-name()='loc']")[0] ?? ''));
            if ($loc === '') {
                continue;
            }
            $lm = trim((string) ($n->xpath("*[local-name()='lastmod']")[0] ?? ''));
            $entries[] = ['loc' => $loc, 'lastmod' => $lm !== '' ? $lm : null];
        }

        return ['type' => $type, 'entries' => $entries];
    }

    /** RSS 2.0 or Atom. @return array<int,array{title:string,url:?string,date:?string}> (date = ISO 8601 or null) */
    public static function parseFeed(string $xml): array
    {
        $doc = self::loadXml($xml);
        if (!$doc) {
            return [];
        }

        $items = [];
        foreach ($doc->xpath("//*[local-name()='item' or local-name()='entry']") ?: [] as $n) {
            $title = trim(html_entity_decode((string) ($n->xpath("*[local-name()='title']")[0] ?? ''), ENT_QUOTES | ENT_HTML5));
            $link  = $n->xpath("*[local-name()='link']")[0] ?? null;
            $url   = $link ? (trim((string) $link['href']) ?: trim((string) $link)) : null;
            $raw   = (string) ($n->xpath("*[local-name()='pubDate' or local-name()='published' or local-name()='updated' or local-name()='date']")[0] ?? '');
            $ts    = $raw !== '' ? strtotime($raw) : false;
            if ($title === '') {
                continue;
            }
            $items[] = ['title' => $title, 'url' => $url ?: null, 'date' => $ts ? gmdate('c', $ts) : null];
        }

        return $items;
    }

    /** @return array{title:?string,description:?string,h1:?string,feed_url:?string} */
    public static function parseHomepage(string $html): array
    {
        $out = ['title' => null, 'description' => null, 'h1' => null, 'feed_url' => null];
        if (trim($html) === '') {
            return $out;
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($dom);

        $out['title'] = self::clean($xp->evaluate('string((//title)[1])'));
        $out['description'] = self::clean($xp->evaluate("string((//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='description']/@content)[1])"));
        $out['h1'] = self::clean($xp->evaluate('string((//h1)[1])'));
        $feed = $xp->evaluate("string((//link[@rel='alternate'][contains(@type,'rss') or contains(@type,'atom')]/@href)[1])");
        $out['feed_url'] = $feed !== '' ? $feed : null;

        return $out;
    }

    /** Resolve a possibly-relative URL against a base. */
    public static function absoluteUrl(string $url, string $base): ?string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $p = parse_url($base);
        if (!$p || empty($p['host'])) {
            return null;
        }
        $origin = ($p['scheme'] ?? 'https') . '://' . $p['host'];
        if (str_starts_with($url, '//')) {
            return ($p['scheme'] ?? 'https') . ':' . $url;
        }

        return $origin . '/' . ltrim($url, '/');
    }

    /** Unigram + bigram frequency over titles. @return array<string,int> term => count, most frequent first */
    public static function topTerms(array $titles, int $limit = 15): array
    {
        $counts = [];
        foreach ($titles as $title) {
            $words = array_values(array_filter(
                preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY),
                fn ($w) => mb_strlen($w) >= 3 && !in_array($w, self::STOPWORDS, true) && !ctype_digit($w)
            ));
            $seen = [];
            foreach ($words as $i => $w) {
                $seen[$w] = true;
                if (isset($words[$i + 1])) {
                    $seen[$w . ' ' . $words[$i + 1]] = true;
                }
            }
            foreach (array_keys($seen) as $t) {          // once per title
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }
        $counts = array_filter($counts, fn ($c) => $c >= 2);
        arsort($counts);

        return array_slice($counts, 0, $limit, true);
    }

    /**
     * Topics competitors cover repeatedly that none of OUR text mentions.
     *
     * @param array<int,array<string,int>> $competitorTerms one term=>count map per competitor
     * @param string[] $ownTexts our post titles / tracked keywords
     * @return array<int,array{term:string,competitors:int,mentions:int}>
     */
    public static function topicGaps(array $competitorTerms, array $ownTexts, int $limit = 10): array
    {
        $own = mb_strtolower(implode(' ', $ownTexts));
        $agg = [];
        foreach ($competitorTerms as $terms) {
            foreach ($terms as $term => $count) {
                $agg[$term]['competitors'] = ($agg[$term]['competitors'] ?? 0) + 1;
                $agg[$term]['mentions']    = ($agg[$term]['mentions'] ?? 0) + $count;
            }
        }

        $gaps = [];
        foreach ($agg as $term => $v) {
            if ($own !== '' && mb_strpos($own, (string) $term) !== false) {
                continue;
            }
            $gaps[] = ['term' => (string) $term] + $v;
        }
        usort($gaps, fn ($a, $b) => [$b['competitors'], $b['mentions']] <=> [$a['competitors'], $a['mentions']]);

        return array_slice($gaps, 0, $limit);
    }

    /**
     * Publishing cadence from dated items. Returns null count when no item has a date
     * (unknown must not look like "publishes nothing").
     *
     * @param array<int,?string> $dates ISO-ish date strings
     * @return array{count:?int,latest:?string}
     */
    public static function cadence(array $dates, int $nowTs, int $days = 30): array
    {
        $ts = array_values(array_filter(array_map(fn ($d) => $d ? strtotime($d) : false, $dates)));
        if (!$ts) {
            return ['count' => null, 'latest' => null];
        }
        $since = $nowTs - $days * 86400;

        return [
            'count'  => count(array_filter($ts, fn ($t) => $t >= $since && $t <= $nowTs + 86400)),
            'latest' => gmdate('c', max($ts)),
        ];
    }

    private static function loadXml(string $xml): ?\SimpleXMLElement
    {
        if (trim($xml) === '') {
            return null;
        }
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $doc ?: null;
    }

    private static function clean(string $s): ?string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        return $s !== '' ? mb_substr($s, 0, 500) : null;
    }
}
