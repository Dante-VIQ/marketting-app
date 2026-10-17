<?php

namespace App\Services\AdPlatforms;

use Illuminate\Http\Client\Factory as Http;

/**
 * Meta Marketing API (Graph). Acts on campaigns by id.
 * Env: META_ADS_ACCESS_TOKEN (required), META_ADS_API_VERSION, META_ADS_CURRENCY_OFFSET.
 *
 * Budget caveat: a campaign-level daily_budget only exists on campaigns using
 * Advantage campaign budget (CBO). Otherwise Meta rejects the call and we surface that error.
 */
class MetaAdsPlatform implements AdPlatformInterface
{
    private array $config;
    private Http $http;

    public function __construct(?array $config = null, ?Http $http = null)
    {
        $this->config = $config ?? config('ad_platforms.meta', []);
        $this->http   = $http ?? app(Http::class);
    }

    public function name(): string
    {
        return 'meta';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['access_token']);
    }

    public function fetchCampaign(string $externalId): array
    {
        $data = $this->call('get', $externalId, ['fields' => 'name,status,effective_status,daily_budget']);

        return [
            'status'       => self::normalizeStatus($data['status'] ?? null),
            'daily_budget' => isset($data['daily_budget']) ? self::fromMinorUnits((int) $data['daily_budget'], $this->offset()) : null,
            'name'         => $data['name'] ?? null,
        ];
    }

    public function pause(string $externalId): void
    {
        $this->call('post', $externalId, ['status' => 'PAUSED']);
    }

    public function resume(string $externalId): void
    {
        $this->call('post', $externalId, ['status' => 'ACTIVE']);
    }

    public function setDailyBudget(string $externalId, float $amount): void
    {
        $this->call('post', $externalId, ['daily_budget' => self::toMinorUnits($amount, $this->offset())]);
    }

    public static function normalizeStatus(?string $s): string
    {
        return match (strtoupper((string) $s)) {
            'ACTIVE' => 'active',
            'PAUSED' => 'paused',
            default  => 'other',
        };
    }

    /** Meta takes budgets in the currency's minor unit (cents => offset 100). */
    public static function toMinorUnits(float $amount, int $offset = 100): int
    {
        return (int) round($amount * $offset);
    }

    public static function fromMinorUnits(int $minor, int $offset = 100): float
    {
        return $minor / $offset;
    }

    private function offset(): int
    {
        return (int) ($this->config['currency_offset'] ?? 100);
    }

    private function call(string $method, string $id, array $params): array
    {
        if (!$this->isConfigured()) {
            throw new AdPlatformException('Meta Ads is not configured (META_ADS_ACCESS_TOKEN missing).');
        }
        if (!preg_match('/^\d+$/', $id)) {
            throw new AdPlatformException("Invalid Meta campaign id '{$id}'.");
        }

        $version = $this->config['api_version'] ?? 'v21.0';
        $url     = "https://graph.facebook.com/{$version}/{$id}";
        $client  = $this->http->withToken($this->config['access_token'])->acceptJson()->timeout(20);

        try {
            $response = $method === 'get' ? $client->get($url, $params) : $client->asForm()->post($url, $params);
        } catch (\Throwable $e) {
            throw new AdPlatformException('Meta request failed: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->successful()) {
            $msg = $response->json('error.message') ?? ('HTTP ' . $response->status());
            throw new AdPlatformException("Meta rejected the request: {$msg}");
        }

        return $response->json() ?? [];
    }
}
