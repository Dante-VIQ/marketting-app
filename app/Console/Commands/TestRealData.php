<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Analytics\GA4Service;
use App\Services\Analytics\AnalyticsCollectorService;
use Illuminate\Console\Command;

class TestRealData extends Command
{
    protected $signature = 'test:real-data {brand_id?}';
    protected $description = 'Test real data collection from GA4 (no mock data)';

    public function handle(GA4Service $ga4Service, AnalyticsCollectorService $collector)
    {
        $brandId = $this->argument('brand_id') ?? 1;
        $brand = Brand::find($brandId);

        if (!$brand) {
            $this->error("Brand not found.");
            return 1;
        }

        $this->info("Testing real data collection for: " . $brand->name);

        // Step 1: Check configuration
        $this->line("\n[1/4] Checking GA4 configuration...");
        if (!$ga4Service->isConfigured($brand)) {
            $this->error("GA4 is not configured for this brand.");
            return 1;
        }
        $this->info("✅ GA4 is configured.");

        // Step 2: Test connection
        $this->line("\n[2/4] Testing GA4 connection...");
        $result = $ga4Service->testConnection($brand);
        if (isset($result['error'])) {
            $this->error("GA4 connection failed: " . $result['error']);
            return 1;
        }
        $this->info("✅ GA4 connection successful.");
        
        if ($result['data']) {
            $this->line("   Visitors: " . ($result['data']['visitors'] ?? 0));
            $this->line("   Revenue: $" . ($result['data']['revenue'] ?? 0));
        }

        // Step 3: Collect data
        $this->line("\n[3/4] Collecting real data...");
        try {
            $collector->collectForBrand($brand);
            $this->info("✅ Data collected successfully.");
        } catch (\Exception $e) {
            $this->error("Collection failed: " . $e->getMessage());
            return 1;
        }

        // Step 4: Verify data
        $this->line("\n[4/4] Verifying stored data...");
        $count = \App\Models\AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->whereDate('date', now()->subDay()->toDateString())
            ->count();

        if ($count > 0) {
            $this->info("✅ " . $count . " snapshots stored.");
            
            // Show a sample
            $sample = \App\Models\AnalyticsSnapshot::where('brand_id', $brand->id)
                ->where('source', 'ga4')
                ->whereDate('date', now()->subDay()->toDateString())
                ->first();
            
            $this->line("   Sample: " . $sample->metric . " = " . $sample->value);
        } else {
            $this->warning("No data stored. This may mean no traffic in the selected period.");
        }

        $this->newLine();
        $this->info("✅ Real data test completed!");
        
        return 0;
    }
}