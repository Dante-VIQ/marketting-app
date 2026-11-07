<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

/** Thin Graph API client. Failures throw MetaApiException; nothing is ever returned as fake data. */
class GraphClient
{
    private const BASE = 'https://graph.facebook.com';

    /**
     * $retries: extra attempts after a connection failure (DNS timeout, dropped connection) on READS only.
     * A write is never repeated, because the first attempt may have reached Meta and a repeat could post twice.
     */
    public function __construct(private ?Factory $http = null, private ?string $token = null, private ?string $version = null,
        private int $retries = 2, private int $retryDelayMs = 800)
    {
        $this->http ??= new Factory();
        $this->token ??= (string) self::cfg('meta.access_token', '');
        $this->version ??= (string) self::cfg('meta.api_version', 'v25.0');
    }

    private static function cfg(string $key, $default = null)
    {
        return function_exists('app') && app()->bound('config') ? config($key, $default) : $default;
    }

    public function token(): string
    {
        return (string) $this->token;
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    /** @param string|null $token override, e.g. a Page access token */
    public function get(string $path, array $params = [], ?string $token = null): array
    {
        return $this->send('get', $path, $params, $token);
    }

    public function post(string $path, array $params = [], ?string $token = null): array
    {
        return $this->send('post', $path, $params, $token);
    }

    /** Follows paging.next up to $maxPages; returns the merged `data` rows. */
    public function paginate(string $path, array $params = [], ?string $token = null, int $maxPages = 5): array
    {
        $rows = [];
        $page = $this->get($path, $params, $token);
        for ($i = 0; ; $i++) {
            $rows = array_merge($rows, $page['data'] ?? []);
            $next = $page['paging']['next'] ?? null;
            if (!$next || $i + 1 >= $maxPages) {
                break;
            }
            $after = $page['paging']['cursors']['after'] ?? null;
            if (!$after) {
                break;
            }
            $page = $this->get($path, $params + ['after' => $after], $token);
        }

        return $rows;
    }

    private function send(string $method, string $path, array $params, ?string $token): array
    {
        $token ??= $this->token;
        if ($token === '') {
            throw new MetaApiException('Meta access token is not configured (META_ACCESS_TOKEN).');
        }

        $url = self::BASE . '/' . $this->version . '/' . ltrim($path, '/');
        $req = $this->http->timeout(30)->acceptJson();
        // Token goes in the Authorization header, never in the URL, so it cannot end up in logs.
        $req = $req->withToken($token);

        $attempts = $method === 'get' ? $this->retries + 1 : 1;
        for ($i = 1; ; $i++) {
            try {
                $res = $method === 'get' ? $req->get($url, $params) : $req->asForm()->post($url, $params);
                break;
            } catch (ConnectionException $e) {
                if ($i >= $attempts) {
                    throw new MetaApiException('Could not reach Meta' . ($attempts > 1 ? " after {$attempts} tries" : '') . ': ' . $e->getMessage());
                }
                usleep($this->retryDelayMs * 1000 * $i);   // 0.8s, then 1.6s
            }
        }

        if (!$res->successful()) {
            throw MetaApiException::fromResponse($res->status(), $res->json());
        }

        return $res->json() ?? [];
    }
}
