<?php

namespace App\Services\Competitors\Discovery;

use Illuminate\Http\Client\Factory as Http;

/** Tavily web search (free tier: 1,000 credits a month, no card). One basic search = 1 credit. Key from .env only. */
class TavilySearch implements SearchProvider
{
    public function __construct(private Http $http, private ?string $key)
    {
    }

    public function configured(): bool
    {
        return is_string($this->key) && trim($this->key) !== '';
    }

    public function search(string $query, int $max = 10): array
    {
        if (!$this->configured()) {
            return [];
        }
        $r = $this->http->withToken($this->key)->timeout(20)->acceptJson()
            ->post('https://api.tavily.com/search', ['query' => $query, 'search_depth' => 'basic', 'max_results' => min(max($max, 1), 20)]);

        if ($r->status() === 401 || $r->status() === 403) {
            throw new \RuntimeException('Tavily rejected the API key. Check TAVILY_API_KEY.');
        }
        if ($r->status() === 429 || $r->status() === 432 || $r->status() === 433) {
            throw new \RuntimeException('Tavily search limit reached for now.');
        }
        if (!$r->successful()) {
            throw new \RuntimeException('Tavily search failed (HTTP ' . $r->status() . ').');
        }

        $out = [];
        foreach ($r->json('results') ?? [] as $row) {
            if (is_string($row['url'] ?? null)) {
                $out[] = ['title' => (string) ($row['title'] ?? ''), 'url' => $row['url'], 'snippet' => mb_substr((string) ($row['content'] ?? ''), 0, 300)];
            }
        }

        return $out;
    }
}
