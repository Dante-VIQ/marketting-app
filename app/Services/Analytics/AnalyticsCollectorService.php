<?php

namespace App\Services\Analytics;

use App\Models\Brand;
use App\Models\AnalyticsSnapshot;
use App\Services\Analytics\GA4Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalyticsCollectorService
{
    protected GA4Service $ga4Service;

    public function __construct(GA4Service $ga4Service)
    {
        $this->ga4Service = $ga4Service;
    }

    /**
     * Collect analytics for a specific brand.
     * 
     * @throws \Exception
     */
    public function collectForBrand(Brand $brand): void
    {
        $date = Carbon::yesterday();

        Log::info('Starting analytics collection', [
            'brand_id' => $brand->id,
            'brand_name' => $brand->name,
            'date' => $date->toDateString(),
        ]);

        // Check if GA4 is configured
        if (!$this->ga4Service->isConfigured($brand)) {
            throw new \Exception(sprintf(
                'GA4 is not configured for brand: %s. Please add GA4 credentials in Brand Settings.',
                $brand->name
            ));
        }

        try {
            // Collect from GA4 (REAL DATA ONLY)
            $this->collectFromGA4($brand, $date);
            
            Log::info('Analytics collection completed', [
                'brand_id' => $brand->id,
                'date' => $date->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics collection failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

/**
 * Collect data from Google Analytics 4.
 * 
 * @throws \Exception
 */
protected function collectFromGA4(Brand $brand, Carbon $date): void
{
    // Get real data from GA4 (30-day window)
    $data = $this->ga4Service->getDataApiData($brand);

    // Get the brand's website URL for building full URLs
    $baseUrl = $brand->website_url ?? 'https://' . $brand->slug . '.com';
    $baseUrl = rtrim($baseUrl, '/');

    // Log the data for debugging
    \Illuminate\Support\Facades\Log::info('GA4 data received for storage', [
        'brand_id' => $brand->id,
        'date' => $date->toDateString(),
        'visitors' => $data['visitors'] ?? 0,
        'visitors_avg' => $data['visitors_avg'] ?? 0,
        'sessions' => $data['sessions'] ?? 0,
        'revenue' => $data['revenue'] ?? 0,
    ]);

    // Store daily average visitors
    $visitors = $data['visitors_avg'] ?? 0;
    $this->storeSnapshot($brand, $date, 'ga4', 'visitors', null, $visitors);
    
    // Store page views (daily average)
    $pageViews = round($data['page_views'] / 30, 0);
    $this->storeSnapshot($brand, $date, 'ga4', 'page_views', null, $pageViews);
    
    // Store sessions (daily average)
    $sessions = $data['sessions'] > 0 ? round($data['sessions'] / 30, 0) : 0;
    $this->storeSnapshot($brand, $date, 'ga4', 'sessions', null, $sessions);
    
    // Store conversions (daily average)
    $conversions = $data['conversions'] > 0 ? round($data['conversions'] / 30, 2) : 0;
    $this->storeSnapshot($brand, $date, 'ga4', 'conversions', null, $conversions);
    
    // Store revenue (daily average)
    $revenue = $data['revenue'] > 0 ? round($data['revenue'] / 30, 2) : 0;
    $this->storeSnapshot($brand, $date, 'ga4', 'revenue', null, $revenue);

    // Store top pages with FULL URLs
    if (!empty($data['top_pages'])) {
        foreach ($data['top_pages'] as $page) {
            // Build full URL
            $path = $page['path'] ?? '/';
            $fullUrl = $this->buildFullUrl($baseUrl, $path);

            $this->storeSnapshot(
                $brand,
                $date,
                'ga4',
                'visitors',
                $fullUrl,
                $page['visitors'] ?? 0
            );
        }
    }

    Log::info('GA4 data stored', [
        'brand_id' => $brand->id,
        'date' => $date->toDateString(),
        'visitors_daily_avg' => $visitors,
        'total_visitors_30day' => $data['visitors'] ?? 0,
    ]);
}

/**
 * Build a full URL from a base URL and path.
 */
protected function buildFullUrl(string $baseUrl, string $path): string
{
    // If already a full URL, return as is
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    // Remove leading slash from path for proper joining
    $path = ltrim($path, '/');

    // Build the full URL
    $fullUrl = $baseUrl . '/' . $path;

    // Normalize: remove double slashes (but not in protocol)
    $fullUrl = preg_replace('/(?<!:)\/+/', '/', $fullUrl);

    // Ensure HTTPS
    $fullUrl = str_replace('http://', 'https://', $fullUrl);

    return $fullUrl;
}

    /**
     * Store a snapshot in the database.
     */
    protected function storeSnapshot(Brand $brand, Carbon $date, string $source, string $metric, ?string $dimension, $value): void
    {
        try {
            AnalyticsSnapshot::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'date' => $date->toDateString(),
                    'source' => $source,
                    'metric' => $metric,
                    'dimension' => $dimension,
                ],
                [
                    'value' => $value,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to store snapshot', [
                'brand_id' => $brand->id,
                'source' => $source,
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
