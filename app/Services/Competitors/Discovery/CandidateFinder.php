<?php

namespace App\Services\Competitors\Discovery;

use App\Services\AI\AiGatewayService;
use App\Services\Competitors\CompetitorParser;

/**
 * Where competitor candidates come from. Each source is optional and one failing never stops the others.
 * Candidates are only leads: nothing here is trusted until the site is fetched and checked.
 */
class CandidateFinder
{
    public function __construct(private ?AiGatewayService $ai = null, private ?SearchProvider $search = null)
    {
    }

    public static function aiPrompt(array $profile): array
    {
        $system = <<<TXT
You help a brand find its real competitors. Return ONLY valid JSON: {"competitors":[{"domain":string,"name":string,"why":string}],"queries":[string]}
RULES:
- Only websites you are confident really exist and are established. Give the bare domain (example.com). Never invent a domain. If unsure, return fewer.
- Competitors = sites that serve the same audience in the same niche and region: both sites that sell what the brand sells AND sites that publish the content its audience reads. No huge general platforms (Wikipedia, YouTube, TripAdvisor, social networks).
- Do not include the brand's own domain. Max 12.
- "why": one plain sentence tied to the brand facts given.
- "queries": 6 searches a person who needs what this brand offers would type into Google. Describe the need; do not include the brand name or any competitor name.
TXT;
        $facts = array_filter($profile, fn ($v, $k) => $k !== 'terms' && $v !== null && $v !== [], ARRAY_FILTER_USE_BOTH);

        return ['system_prompt' => $system, 'user_prompt' => json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'temperature' => 0.3, 'max_tokens' => 1500, 'response_format' => 'json'];
    }

    /** @return array{competitors:array<int,array{domain:string,name:?string,why:?string}>,queries:string[]}|null */
    public static function parseAi(string $content, ?string $ownDomain = null): ?array
    {
        $c = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $d = json_decode($c, true);
        if (!is_array($d) && preg_match('/\{[\s\S]*\}/', $c, $m)) {
            $d = json_decode($m[0], true);
        }
        if (!is_array($d)) {
            return null;
        }
        $comps = [];
        foreach ((array) ($d['competitors'] ?? []) as $row) {
            $dom = is_array($row) && is_string($row['domain'] ?? null) ? CompetitorParser::normalizeDomain($row['domain']) : null;
            if ($dom && $dom !== $ownDomain && !isset($comps[$dom])) {
                $comps[$dom] = ['domain' => $dom, 'name' => is_string($row['name'] ?? null) ? mb_substr(trim($row['name']), 0, 100) : null,
                    'why' => is_string($row['why'] ?? null) ? mb_substr(trim($row['why']), 0, 300) : null];
            }
        }
        $queries = array_values(array_filter(array_map(fn ($q) => is_string($q) ? mb_substr(trim($q), 0, 120) : null, (array) ($d['queries'] ?? []))));

        return ['competitors' => array_values($comps), 'queries' => array_slice($queries, 0, 8)];
    }

    /**
     * Candidates by domain: ['sources'=>[...], 'name'=>?, 'why'=>?, 'queries'=>int, 'snippets'=>[...]].
     * @return array{candidates:array,notes:string[],used_search:bool}
     */
    public function find(array $profile): array
    {
        $cands = [];
        $notes = [];
        $own = $profile['own_domain'] ?? null;
        $add = function (string $domain, string $source, array $extra = []) use (&$cands, $own) {
            if ($domain === $own) {
                return;
            }
            $cands[$domain] ??= ['sources' => [], 'name' => null, 'why' => null, 'queries' => 0, 'snippets' => []];
            $cands[$domain]['sources'] = array_values(array_unique([...$cands[$domain]['sources'], $source]));
            foreach (['name', 'why'] as $k) {
                $cands[$domain][$k] ??= $extra[$k] ?? null;
            }
        };

        // 1) Competitors the team already wrote down.
        foreach ($profile['noted_competitors'] ?? [] as $d) {
            $add($d, 'your_notes');
        }

        // 2) AI: candidates plus the searches a customer would make.
        $queries = [];
        try {
            $res = ($this->ai ?? new AiGatewayService())->generate(self::aiPrompt($profile));
            $parsed = !empty($res['success']) ? self::parseAi((string) $res['content'], $own) : null;
            if ($parsed) {
                foreach ($parsed['competitors'] as $c) {
                    $add($c['domain'], 'ai', $c);
                }
                $queries = $parsed['queries'];
            } else {
                $notes[] = 'The AI answer could not be used, so AI suggestions were skipped this time.';
            }
        } catch (\Throwable $e) {
            report($e);
            $notes[] = 'The AI provider did not answer, so AI suggestions were skipped this time.';
        }

        // 3) Web search on real customer-style queries (AI-written) and the brand's own ranking keywords.
        $used = false;
        if ($this->search && $this->search->configured()) {
            $queries = array_slice(array_values(array_unique([...$queries, ...array_slice($profile['ranking_keywords'] ?? [], 0, 3)])), 0, (int) config('competitors.max_search_queries', 6));
            foreach ($queries as $q) {
                try {
                    $seen = [];
                    foreach ($this->search->search($q, 10) as $hit) {
                        $dom = CompetitorParser::normalizeDomain((string) parse_url($hit['url'], PHP_URL_HOST));
                        if (!$dom || isset($seen[$dom])) {
                            continue;
                        }
                        $seen[$dom] = true;
                        $add($dom, 'search');
                        $cands[$dom]['queries']++;
                        if (count($cands[$dom]['snippets']) < 3) {
                            $cands[$dom]['snippets'][] = $hit['title'] . '. ' . $hit['snippet'];
                        }
                    }
                    $used = true;
                } catch (\Throwable $e) {
                    $notes[] = 'Web search: ' . $e->getMessage();
                    break;   // a rejected key or exhausted limit will fail every query
                }
            }
        } else {
            $notes[] = 'Web search is not connected, so results come from the brand notes and AI only. Add TAVILY_API_KEY (free at tavily.com) for evidence from real searches.';
        }

        return ['candidates' => $cands, 'notes' => $notes, 'used_search' => $used];
    }
}
