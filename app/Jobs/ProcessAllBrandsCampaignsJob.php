<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\Campaign;
use App\Services\Campaign\CampaignManagerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAllBrandsCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CampaignManagerService $campaignManager): void
    {
        $brands = Brand::where('is_active', true)->get();

        Log::info('Processing campaigns for all active brands', ['count' => $brands->count()]);

        foreach ($brands as $brand) {
            try {
                // Get active campaigns
                $campaigns = Campaign::where('brand_id', $brand->id)
                    ->where('status', 'active')
                    ->get();

                foreach ($campaigns as $campaign) {
                    // Generate recommendations for each campaign
                    $campaignManager->generateRecommendations($campaign);
                }

                Log::info('Campaigns processed for brand', [
                    'brand_id' => $brand->id,
                    'count' => $campaigns->count(),
                ]);
            } catch (\Exception $e) {
                Log::error('Error processing campaigns for brand', [
                    'brand_id' => $brand->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}