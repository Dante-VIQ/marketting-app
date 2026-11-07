<?php

namespace App\Services\Meta;

/**
 * Free audience research from Meta's targeting tools: how many people are interested in a topic, and how many of
 * them are in the countries we care about. Sizes are Meta's estimates (ranges), shown as ranges.
 */
class AudienceResearch
{
    public function __construct(private GraphClient $graph) {}

    /** @return array<int,array{id:string,name:string,low:?int,high:?int,topic:?string}> */
    public function interests(string $query, int $limit = 10): array
    {
        $rows = $this->graph->get('search', ['type' => 'adinterest', 'q' => $query, 'limit' => $limit])['data'] ?? [];

        return array_map(fn ($r) => [
            'id' => (string) $r['id'], 'name' => $r['name'] ?? '',
            'low' => isset($r['audience_size_lower_bound']) ? (int) $r['audience_size_lower_bound'] : null,
            'high' => isset($r['audience_size_upper_bound']) ? (int) $r['audience_size_upper_bound'] : null,
            'topic' => $r['topic'] ?? null,
        ], $rows);
    }

    /**
     * People in $countries interested in $interestId, from the ad account's delivery estimate (Meta's current way to size an audience).
     * Returns nulls when Meta does not report a size; never a guess.
     * @return array{low:?int,high:?int}
     */
    public function reachEstimate(string $adAccount, string $interestId, array $countries): array
    {
        $spec = ['geo_locations' => ['countries' => array_values($countries)], 'interests' => [['id' => $interestId]]];
        $res = $this->graph->get($adAccount . '/delivery_estimate', ['targeting_spec' => json_encode($spec), 'optimization_goal' => 'REACH']);
        $d = $res['data'][0] ?? [];

        return [
            'low' => isset($d['estimate_mau_lower_bound']) ? (int) $d['estimate_mau_lower_bound'] : null,
            'high' => isset($d['estimate_mau_upper_bound']) ? (int) $d['estimate_mau_upper_bound'] : null,
        ];
    }
}
