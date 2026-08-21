<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateData;
use App\Models\Brand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AffiliateDataCollectorService
{
    protected array $networks = [
        'travel_payouts' => [
            'api_endpoint' => 'https://api.travelpayouts.com/statistics/v1/',
            'requires_api_key' => true,
        ],
    'bonusarrive' => [
        'api_endpoint' => 'https://www.bonusarrive.com/slapi/service/',
        'requires_api_key' => true,
    ],
        'awin' => [
            'api_endpoint' => 'https://api.awin.com/v2/transactions',
            'requires_api_key' => true,
        ],
    ];

    public function collectForBrand(Brand $brand): void
    {
        $config = is_array($brand->config) 
            ? $brand->config 
            : json_decode($brand->config ?? '[]', true);

        // Use CarbonImmutable to completely prevent date mutation side-effects
        $date = CarbonImmutable::yesterday();
        $failures = [];

        foreach ($this->networks as $network => $networkConfig) {
            $apiKey = $this->extractApiKey($config, $network);

            if (empty($apiKey)) {
                Log::warning('Affiliate network not configured, skipping', [
                    'brand_id' => $brand->id,
                    'network'  => $network,
                ]);
                continue;
            }

            try {
                $data = $this->fetchData($config, $network, $apiKey, $date);

                if (empty($data)) {
                    Log::warning('No affiliate data returned from API', [
                        'brand_id' => $brand->id,
                        'network'  => $network,
                    ]);
                    continue;
                }

                $this->storeData($brand, $network, $date, $data);

                Log::info('Affiliate data collected successfully', [
                    'brand_id' => $brand->id,
                    'network'  => $network,
                    'clicks'   => $data['clicks'] ?? 0,
                ]);
            } catch (\Throwable $e) {
                Log::error('Affiliate data collection failed for network', [
                    'brand_id' => $brand->id,
                    'network'  => $network,
                    'error'    => $e->getMessage(),
                ]);

                $failures[$network] = $e->getMessage();
            }
        }

        $configuredCount = $this->getConfiguredNetworksCount($config);

        if (!empty($failures) && count($failures) === $configuredCount) {
            throw new \RuntimeException(
                "All configured affiliate networks failed for brand ID {$brand->id}: " . 
                json_encode($failures, JSON_UNESCAPED_SLASHES)
            );
        }
    }

    protected function extractApiKey(array $config, string $network): ?string
    {
        $rawKey = $config[$network . '_api_key'] ?? null;

        if (is_array($rawKey)) {
            return $rawKey['key'] ?? $rawKey[0] ?? null;
        }

        return $rawKey;
    }

    protected function getConfiguredNetworksCount(array $config): int
    {
        $count = 0;
        foreach ($this->networks as $network => $netConfig) {
            if (!empty($this->extractApiKey($config, $network))) {
                $count++;
            }
        }
        return $count;
    }

    protected function fetchData(array $config, string $network, string $apiKey, CarbonImmutable $date): array
    {
        return match ($network) {
            'travel_payouts' => $this->fetchTravelPayouts($apiKey, $date),
            'awin'           => $this->fetchAwin($apiKey, $config['awin_publisher_id'] ?? null, $date),
            'bonusarrive'    => $this->fetchBonusArrive($apiKey, $date),
            default          => throw new \InvalidArgumentException("Unsupported network: {$network}"),
        };
    }

public function fetchTravelPayouts(string $apiKey, CarbonImmutable $date): array
{
    // Appended execute_query to resolve the 404 issue
    $endpoint = 'https://api.travelpayouts.com/statistics/v1/execute_query';
    $dateStr = $date->toDateString();
    
    $payload = [
        'fields' => ['action_id', 'price_usd', 'paid_profit_usd', 'state', 'date', 'created_at'],
        'filters' => [
            ['field' => 'date', 'op' => 'ge', 'value' => $dateStr],
            ['field' => 'date', 'op' => 'le', 'value' => $dateStr],
            ['field' => 'type', 'op' => 'eq', 'value' => 'action'],
        ],
        'offset' => 0,
        'limit' => 100,
    ];
    
    $response = Http::withHeaders([
        'X-Access-Token' => $apiKey,
        'Content-Type' => 'application/json',
    ])->timeout(30)->post($endpoint, $payload);
    
    if ($response->failed()) {
        throw new \RuntimeException("Travelpayouts API returned HTTP {$response->status()}: " . $response->body());
    }
    
    $results = $response->json('results') ?? [];
    
    $totalBookings = 0;
    $totalCommission = 0.0;
    $totalRevenue = 0.0;
    
    foreach ($results as $item) {
        if (in_array($item['state'] ?? '', ['paid', 'processing'], true)) {
            $totalBookings++;
            $totalCommission += (float) ($item['paid_profit_usd'] ?? 0);
            $totalRevenue += (float) ($item['price_usd'] ?? 0);
        }
    }
    
    $clickData = $this->fetchTravelPayoutsClicks($apiKey, $date);
    
    return [
        'clicks' => $clickData['clicks'] ?? 0,
        'bookings' => $totalBookings,
        'commission' => $totalCommission,
        'revenue' => $totalRevenue,
    ];
}

private function fetchTravelPayoutsClicks(string $apiKey, CarbonImmutable $date): array
{
    // Appended execute_query to resolve the 404 issue
    $endpoint = 'https://api.travelpayouts.com/statistics/v1/execute_query';
    $dateStr = $date->toDateString();
    
    $payload = [
        'fields' => ['redirect_count', 'inits_count', 'searches_count'],
        'filters' => [
            ['field' => 'date', 'op' => 'ge', 'value' => $dateStr],
            ['field' => 'date', 'op' => 'le', 'value' => $dateStr],
        ],
        'group' => ['date'],
    ];
    
    $response = Http::withHeaders([
        'X-Access-Token' => $apiKey,
        'Content-Type' => 'application/json',
    ])->timeout(30)->post($endpoint, $payload);
    
    if ($response->failed()) {
        Log::warning('Travelpayouts clicks API failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return ['clicks' => 0];
    }
    
    $results = $response->json('results') ?? [];
    $totalClicks = 0;

    foreach ($results as $item) {
        $totalClicks += (int) ($item['redirect_count'] ?? 0);
    }
    
    return ['clicks' => $totalClicks];
}

    public function fetchAwin(string $apiKey, ?string $publisherId, CarbonImmutable $date): array
    {
        if (!$publisherId) {
            throw new \InvalidArgumentException("Awin Publisher ID is required. Please add 'awin_publisher_id' to brand config.");
        }

        $endpoint = "https://api.awin.com/publishers/{$publisherId}/transactions/";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->timeout(30)->get($endpoint, [
            'startDate' => $date->startOfDay()->toIso8601String(),
            'endDate'   => $date->endOfDay()->toIso8601String(),
            'timezone'  => 'UTC',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Awin API returned HTTP {$response->status()}: " . $response->body());
        }

        $res = $response->json() ?? [];
        $transactions = is_array($res) && isset($res[0]) ? $res : ($res['transactions'] ?? []);

        return [
            'clicks'     => 0,
            'bookings'   => count($transactions),
            'commission' => (float) array_sum(array_column($transactions, 'commissionAmount')),
            'revenue'    => (float) array_sum(array_column($transactions, 'saleAmount')),
        ];
    }

/**
 * BonusArrive API Driver.
 * Uses the correct endpoints from the documentation.
 */
public function fetchBonusArrive(string $apiKey, CarbonImmutable $date): array
{
        if (!$date instanceof CarbonImmutable) {
        $date = CarbonImmutable::parse($date);
    }

    $baseUrl = 'https://www.bonusarrive.com/slapi/service/';
    $endpoint = $baseUrl . 'clickreports';
    
    $payload = [
        'page' => 1,
        'per_page' => 100,
        'begin_date' => $date->toDateString(),
        'end_date' => $date->toDateString(),
    ];
    
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json;charset=utf-8',
    ])->timeout(30)->post($endpoint, $payload);
    
    if ($response->failed()) {
        throw new \RuntimeException("BonusArrive API returned HTTP {$response->status()}: " . $response->body());
    }
    
    $res = $response->json();
    
    // Log the response for debugging
    Log::info('BonusArrive API response', ['data' => $res]);
    
    // Parse the response - structure may vary based on actual API response
    // Common structures:
    // - { "data": { "clicks": 10, "conversions": 2, "payout": 50.00 } }
    // - { "clicks": 10, "conversions": 2, "payout": 50.00 }
    // - { "results": [ { "clicks": 10, "conversions": 2 } ] }
    
    $payload = $res['data'] ?? $res;
    
    // If it's an array of results, sum them up
    if (isset($payload[0]) && is_array($payload[0])) {
        $totalClicks = 0;
        $totalConversions = 0;
        $totalPayout = 0;
        $totalRevenue = 0;
        
        foreach ($payload as $item) {
            $totalClicks += (int) ($item['clicks'] ?? 0);
            $totalConversions += (int) ($item['conversions'] ?? $item['bookings'] ?? 0);
            $totalPayout += (float) ($item['payout'] ?? $item['commission'] ?? 0);
            $totalRevenue += (float) ($item['revenue'] ?? 0);
        }
        
        return [
            'clicks' => $totalClicks,
            'bookings' => $totalConversions,
            'commission' => $totalPayout,
            'revenue' => $totalRevenue,
        ];
    }
    
    // Handle single object response
    return [
        'clicks' => (int) ($payload['clicks'] ?? 0),
        'bookings' => (int) ($payload['conversions'] ?? $payload['bookings'] ?? 0),
        'commission' => (float) ($payload['payout'] ?? $payload['commission'] ?? 0),
        'revenue' => (float) ($payload['revenue'] ?? 0),
    ];
}

    protected function storeData(Brand $brand, string $network, CarbonImmutable $date, array $data): void
    {
        $clicks = (int) ($data['clicks'] ?? 0);
        $bookings = (int) ($data['bookings'] ?? 0);

        $conversionRate = $clicks > 0
            ? round(($bookings / $clicks) * 100, 2)
            : 0;

        AffiliateData::updateOrCreate(
            [
                'brand_id' => $brand->id,
                'network'  => $network,
                'date'     => $date->toDateString(),
            ],
            [
                'clicks'            => $clicks,
                'bookings'          => $bookings,
                'commission_earned' => $data['commission'] ?? 0,
                'revenue_generated' => $data['revenue'] ?? 0,
                'conversion_rate'   => $conversionRate,
                'metadata'          => $data['metadata'] ?? null,
            ]
        );
    }

    public function getPerformanceSummary(Brand $brand): array
    {
        return AffiliateData::where('brand_id', $brand->id)
            ->select('network')
            ->selectRaw('SUM(clicks) as total_clicks')
            ->selectRaw('SUM(bookings) as total_bookings')
            ->selectRaw('SUM(commission_earned) as total_commission')
            ->selectRaw('SUM(revenue_generated) as total_revenue')
            ->groupBy('network')
            ->get()
            ->keyBy('network')
            ->map(fn ($row) => [
                'clicks'            => (int) $row->total_clicks,
                'bookings'          => (int) $row->total_bookings,
                'commission_earned' => (float) $row->total_commission,
                'revenue_generated' => (float) $row->total_revenue,
            ])
            ->toArray();
    }

    public function getDashboardData(Brand $brand, int $days = 30): array
    {
        $data = AffiliateData::where('brand_id', $brand->id)
            ->where('date', '>=', CarbonImmutable::today()->subDays($days)->toDateString())
            ->orderBy('date', 'desc')
            ->get();

        $totalClicks = (int) $data->sum('clicks');
        $totalBookings = (int) $data->sum('bookings');

        $overallConversionRate = $totalClicks > 0
            ? round(($totalBookings / $totalClicks) * 100, 2)
            : 0;

        return [
            'daily'               => $data,
            'summary'             => $this->getPerformanceSummary($brand),
            'total_clicks'        => $totalClicks,
            'total_bookings'      => $totalBookings,
            'total_commission'    => (float) $data->sum('commission_earned'),
            'total_revenue'       => (float) $data->sum('revenue_generated'),
            'avg_conversion_rate' => $overallConversionRate,
        ];
    }
}