<?php

namespace App\Services\Analytics;

use App\Models\Brand;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GA4Service
{
    protected ?string $measurementId = null;
    protected ?string $apiSecret = null;
    protected ?string $propertyId = null;
    protected ?string $serviceAccountJson = null;

    public function __construct()
    {
        $this->measurementId = env('GA4_MEASUREMENT_ID');
        $this->apiSecret = env('GA4_API_SECRET');
        $this->propertyId = env('GA4_PROPERTY_ID');
        $this->serviceAccountJson = storage_path('app/ga4-service-account.json');
    }

    /**
     * Check if GA4 is properly configured for a given brand.
     */
    public function isConfigured(Brand $brand): bool
    {
        $config = $brand->config ?? [];
        $propertyId = $config['ga4_property_id'] ?? $this->propertyId;

        return !empty($propertyId) && file_exists($this->serviceAccountJson);
    }

    /**
     * Diagnostic endpoint to verify authentication and fetch sample data.
     */
    public function testConnection(Brand $brand): array
    {
        $config = $brand->config ?? [];
        $serviceAccountExists = file_exists($this->serviceAccountJson);

        $result = [
            'configured'             => $this->isConfigured($brand),
            'property_id'            => $config['ga4_property_id'] ?? $this->propertyId ?? 'Not set',
            'measurement_id'         => $config['ga4_measurement_id'] ?? $this->measurementId ?? 'Not set',
            'api_secret'             => !empty($config['ga4_api_secret'] ?? $this->apiSecret) ? 'Set (hidden)' : 'Not set',
            'service_account_exists' => $serviceAccountExists,
            'service_account_path'   => $this->serviceAccountJson,
            'data'                   => null,
            'error'                  => null,
        ];

        if ($result['configured']) {
            try {
                $result['data'] = $this->getDashboardData($brand);
            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Get a unified payload containing Realtime, Historical, and Channel breakdown data.
     * 
     * @throws \Exception
     */
    public function getDashboardData(Brand $brand, string $startDate = '30daysAgo', string $endDate = 'today'): array
    {
        $propertyId = $brand->config['ga4_property_id'] ?? $this->propertyId;

        if (!$propertyId) {
            throw new \Exception('GA4 Property ID is not configured.');
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception('Could not obtain GA4 access token.');
        }

        return [
            'realtime'  => $this->fetchRealtimeData($propertyId, $accessToken),
            'overview'  => $this->fetchHistoricalOverview($propertyId, $accessToken, $startDate, $endDate),
            'channels'  => $this->fetchChannelBreakdown($propertyId, $accessToken, $startDate, $endDate),
            'top_pages' => $this->fetchTopPages($propertyId, $accessToken, $startDate, $endDate),
        ];
    }

/**
 * Get analytics data from Google Analytics Data API.
 * 
 * @throws \Exception
 */
public function getDataApiData(Brand $brand): array
{
    $config = $brand->config;
    $propertyId = $config['ga4_property_id'] ?? $this->propertyId;

    if (!$propertyId) {
        throw new \Exception('GA4 Property ID is not configured. Please add it in Brand Settings.');
    }

    $accessToken = $this->getAccessToken();
    if (!$accessToken) {
        throw new \Exception('Could not get GA4 access token. Check your service account configuration.');
    }

    // 1. Fetch Overall Property Totals
    $totalsPayload = [
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'metrics' => [
            ['name' => 'activeUsers'],
            ['name' => 'sessions'],
            ['name' => 'keyEvents'],
            ['name' => 'totalRevenue'],
            ['name' => 'screenPageViews'],
        ],
    ];

    $totalsResponse = Http::withHeaders([
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
    ])->post(
        "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport",
        $totalsPayload
    );

    if (!$totalsResponse->successful()) {
        $errorBody = $totalsResponse->json();
        $errorMessage = $errorBody['error']['message'] ?? $totalsResponse->body();
        
        Log::error('GA4 totals API request failed', [
            'brand_id' => $brand->id,
            'status' => $totalsResponse->status(),
            'body' => $totalsResponse->body(),
        ]);
        
        throw new \Exception('GA4 API error: ' . $errorMessage);
    }

    $totalsData = $totalsResponse->json();

    // 2. Fetch Top Pages Breakdown
    $pagesPayload = [
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'pagePath']],
        'metrics'    => [['name' => 'activeUsers']],
        'orderBys'   => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
        'limit'      => 10,
    ];

    $pagesResponse = Http::withHeaders([
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
    ])->post(
        "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport",
        $pagesPayload
    );

    if (!$pagesResponse->successful()) {
        Log::error('GA4 pages API request failed', [
            'status' => $pagesResponse->status(),
            'body' => $pagesResponse->body(),
        ]);
        // Don't throw here - we still have totals data
    }

    $pagesData = $pagesResponse->successful() ? $pagesResponse->json() : [];

    return $this->parseCombinedResponse($totalsData, $pagesData);
}

    /**
     * Parse combined totals and pages response.
     */
    protected function parseCombinedResponse(array $totalsData, array $pagesData): array
    {
        $result = [
            'visitors'     => 0,
            'page_views'   => 0,
            'sessions'     => 0,
            'conversions'  => 0,
            'revenue'      => 0,
            'visitors_avg' => 0,
            'top_pages'    => [],
            'sources'      => [],
            'trends'       => [],
        ];

        if (isset($totalsData['rows'][0]['metricValues'])) {
            $metrics = $totalsData['rows'][0]['metricValues'];

            $result['visitors']    = (int)($metrics[0]['value'] ?? 0);
            $result['sessions']    = (int)($metrics[1]['value'] ?? 0);
            $result['conversions'] = (int)($metrics[2]['value'] ?? 0);
            $result['revenue']     = (float)($metrics[3]['value'] ?? 0);
            $result['page_views']  = (int)($metrics[4]['value'] ?? 0);
            $result['visitors_avg'] = round($result['visitors'] / 30, 0);
        } else {
            Log::warning('No rows in GA4 totals response', ['response' => $totalsData]);
        }

        if (isset($pagesData['rows']) && !empty($pagesData['rows'])) {
            foreach ($pagesData['rows'] as $row) {
                $dimension = $row['dimensionValues'][0]['value'] ?? '/';
                $visitors = (int)($row['metricValues'][0]['value'] ?? 0);
                $result['top_pages'][] = [
                    'path'           => $dimension,
                    'dimension'      => $dimension,
                    'visitors'       => $visitors,
                    'total_visitors' => $visitors,
                ];
            }
        }

        return $result;
    }

    /**
     * 1. Fetch Realtime Data (Last 30 Minutes)
     */
    protected function fetchRealtimeData(string $propertyId, string $accessToken): array
    {
        $payload = [
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'screenPageViews'],
            ],
            'dimensions' => [
                ['name' => 'minutesAgo'],
            ],
            'metricAggregations' => ['TOTAL'],
        ];

        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $accessToken])
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runRealtimeReport", $payload);

        if (!$response->successful()) {
            Log::error('GA4 Realtime API Error', ['body' => $response->body()]);
            return ['total_active_users' => 0, 'per_minute' => []];
        }

        $data = $response->json();
        $totalUsers = (int)($data['totals'][0]['metricValues'][0]['value'] ?? 0);

        $minuteMap = array_fill(0, 30, 0);
        if (isset($data['rows'])) {
            foreach ($data['rows'] as $row) {
                $min = (int)($row['dimensionValues'][0]['value'] ?? 0);
                if ($min >= 0 && $min < 30) {
                    $minuteMap[$min] += (int)($row['metricValues'][0]['value'] ?? 0);
                }
            }
        }

        $perMinute = [];
        foreach ($minuteMap as $minute => $users) {
            $perMinute[] = ['minutes_ago' => $minute, 'active_users' => $users];
        }

        return [
            'total_active_users' => $totalUsers,
            'per_minute'         => $perMinute,
        ];
    }

    /**
     * 2. Fetch Overall Historical Metrics (Users, Sessions, Engagement, Revenue)
     */
    protected function fetchHistoricalOverview(string $propertyId, string $accessToken, string $startDate, string $endDate): array
    {
        $payload = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'newUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'bounceRate'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'totalRevenue'],
            ],
        ];

        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $accessToken])
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", $payload);

        if (!$response->successful() || !isset($response->json()['rows'][0]['metricValues'])) {
            return [];
        }

        $metrics = $response->json()['rows'][0]['metricValues'];

        return [
            'active_users'           => (int)($metrics[0]['value'] ?? 0),
            'new_users'              => (int)($metrics[1]['value'] ?? 0),
            'sessions'               => (int)($metrics[2]['value'] ?? 0),
            'page_views'             => (int)($metrics[3]['value'] ?? 0),
            'bounce_rate'            => round(((float)($metrics[4]['value'] ?? 0)) * 100, 2) . '%',
            'avg_session_duration_s' => round((float)($metrics[5]['value'] ?? 0), 1),
            'total_revenue'          => (float)($metrics[6]['value'] ?? 0),
        ];
    }

    /**
     * 3. Fetch Acquisition Channels (SEO / Organic, Direct, Paid, Referral)
     */
    protected function fetchChannelBreakdown(string $propertyId, string $accessToken, string $startDate, string $endDate): array
    {
        $payload = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
            'metrics'    => [
                ['name' => 'sessions'],
                ['name' => 'activeUsers'],
            ],
            'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        ];

        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $accessToken])
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", $payload);

        $channels = [];
        if (isset($response->json()['rows'])) {
            foreach ($response->json()['rows'] as $row) {
                $channels[] = [
                    'channel'  => $row['dimensionValues'][0]['value'] ?? 'Direct',
                    'sessions' => (int)($row['metricValues'][0]['value'] ?? 0),
                    'users'    => (int)($row['metricValues'][1]['value'] ?? 0),
                ];
            }
        }

        return $channels;
    }

    /**
     * 4. Fetch Top Page Paths
     */
    protected function fetchTopPages(string $propertyId, string $accessToken, string $startDate, string $endDate): array
    {
        $payload = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'pagePath']],
            'metrics'    => [
                ['name' => 'screenPageViews'],
                ['name' => 'activeUsers'],
            ],
            'orderBys'   => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit'      => 10,
        ];

        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $accessToken])
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", $payload);

        $pages = [];
        if (isset($response->json()['rows'])) {
            foreach ($response->json()['rows'] as $row) {
                $pages[] = [
                    'path'  => $row['dimensionValues'][0]['value'] ?? '/',
                    'views' => (int)($row['metricValues'][0]['value'] ?? 0),
                    'users' => (int)($row['metricValues'][1]['value'] ?? 0),
                ];
            }
        }

        return $pages;
    }

    /**
     * Fetch OAuth2 access token via Service Account Credentials.
     */
    protected function getAccessToken(): ?string
    {
        if (!file_exists($this->serviceAccountJson)) {
            Log::error('GA4 service account file missing: ' . $this->serviceAccountJson);
            return null;
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($this->serviceAccountJson);
            $client->addScope('https://www.googleapis.com/auth/analytics.readonly');
            $client->fetchAccessTokenWithAssertion();

            return $client->getAccessToken()['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('GA4 Authentication Error: ' . $e->getMessage());
            return null;
        }
    }
}