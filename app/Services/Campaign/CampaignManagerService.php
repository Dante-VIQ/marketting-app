<?php

namespace App\Services\Campaign;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\AiAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignManagerService
{
    /**
     * Create a new campaign.
     */
    public function createCampaign(array $data, Brand $brand): Campaign
    {
        $campaign = Campaign::create([
            'brand_id' => $brand->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . uniqid(),
            'type' => $data['type'] ?? 'other',
            'description' => $data['description'] ?? null,
            'budget' => $data['budget'] ?? null,
            'start_date' => $data['start_date'] ?? Carbon::today(),
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'settings' => $data['settings'] ?? null,
        ]);

        Log::info('Campaign created', [
            'campaign_id' => $campaign->id,
            'brand_id' => $brand->id,
            'name' => $campaign->name,
        ]);

        return $campaign;
    }

    /**
     * Update campaign metrics.
     */
    public function updateMetrics(Campaign $campaign, array $metrics): void
    {
        $campaign->update([
            'spent' => $metrics['spent'] ?? $campaign->spent,
            'revenue' => $metrics['revenue'] ?? $campaign->revenue,
            'clicks' => $metrics['clicks'] ?? $campaign->clicks,
            'impressions' => $metrics['impressions'] ?? $campaign->impressions,
            'leads' => $metrics['leads'] ?? $campaign->leads,
            'conversions' => $metrics['conversions'] ?? $campaign->conversions,
        ]);

        Log::info('Campaign metrics updated', [
            'campaign_id' => $campaign->id,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Calculate campaign ROI.
     */
    public function calculateROI(Campaign $campaign): array
    {
        $roi = 0;
        $roiPercentage = 0;

        if ($campaign->spent > 0) {
            $roi = $campaign->revenue - $campaign->spent;
            $roiPercentage = ($roi / $campaign->spent) * 100;
        }

        $costPerLead = $campaign->leads > 0 
            ? $campaign->spent / $campaign->leads 
            : 0;

        $costPerConversion = $campaign->conversions > 0 
            ? $campaign->spent / $campaign->conversions 
            : 0;

        return [
            'roi' => round($roi, 2),
            'roi_percentage' => round($roiPercentage, 2),
            'cost_per_lead' => round($costPerLead, 2),
            'cost_per_conversion' => round($costPerConversion, 2),
            'conversion_rate' => $campaign->leads > 0 
                ? round(($campaign->conversions / $campaign->leads) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Generate AI recommendations for a campaign.
     */
    public function generateRecommendations(Campaign $campaign): array
    {
        $roiData = $this->calculateROI($campaign);
        $recommendations = [];

        // Budget recommendations
        if ($roiData['roi_percentage'] > 50) {
            $recommendations[] = [
                'type' => 'budget',
                'message' => "Increase budget for {$campaign->name} - ROI is excellent at {$roiData['roi_percentage']}%",
                'priority' => 'high',
            ];
        } elseif ($roiData['roi_percentage'] < 0) {
            $recommendations[] = [
                'type' => 'budget',
                'message' => "Pause or reduce budget for {$campaign->name} - ROI is negative at {$roiData['roi_percentage']}%",
                'priority' => 'critical',
            ];
        }

        // Cost recommendations
        if ($roiData['cost_per_conversion'] > 100) {
            $recommendations[] = [
                'type' => 'optimization',
                'message' => "Cost per conversion is high (\${$roiData['cost_per_conversion']}). Consider A/B testing ad creatives.",
                'priority' => 'medium',
            ];
        }

        // Conversion rate recommendations
        if ($roiData['conversion_rate'] < 2) {
            $recommendations[] = [
                'type' => 'conversion',
                'message' => "Conversion rate is low ({$roiData['conversion_rate']}%). Review landing page and offer.",
                'priority' => 'high',
            ];
        }

        // Create AI actions from recommendations
        foreach ($recommendations as $rec) {
            AiAction::create([
                'brand_id' => $campaign->brand_id,
                'brief_id' => null,
                'title' => ucfirst($rec['type']) . ' Recommendation: ' . $campaign->name,
                'category' => 'campaign',
                'description' => $rec['message'],
                'estimated_impact' => $rec['priority'] === 'critical' ? 2000 : ($rec['priority'] === 'high' ? 1000 : 500),
                'priority' => $rec['priority'] === 'critical' ? 5 : ($rec['priority'] === 'high' ? 4 : 3),
                'status' => 'pending',
            ]);
        }

        return $recommendations;
    }

    /**
     * Get campaign performance summary.
     */
    public function getPerformanceSummary(Brand $brand): array
    {
        $active = Campaign::where('brand_id', $brand->id)
            ->where('status', 'active')
            ->get();

        $completed = Campaign::where('brand_id', $brand->id)
            ->where('status', 'completed')
            ->get();

        $totalSpent = $active->sum('spent') + $completed->sum('spent');
        $totalRevenue = $active->sum('revenue') + $completed->sum('revenue');
        $totalLeads = $active->sum('leads') + $completed->sum('leads');

        return [
            'active_count' => $active->count(),
            'completed_count' => $completed->count(),
            'total_spent' => $totalSpent,
            'total_revenue' => $totalRevenue,
            'total_leads' => $totalLeads,
            'overall_roi' => $totalSpent > 0 
                ? round((($totalRevenue - $totalSpent) / $totalSpent) * 100, 2) 
                : 0,
        ];
    }
}