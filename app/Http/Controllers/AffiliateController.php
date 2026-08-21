<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\Affiliate\AffiliateDataCollectorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AffiliateController extends Controller
{
    protected AffiliateDataCollectorService $affiliateService;

    public function __construct(AffiliateDataCollectorService $affiliateService)
    {
        $this->affiliateService = $affiliateService;
    }

    /**
     * Display affiliate dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        // Get affiliate data for the last 30 days
        $dashboardData = $this->affiliateService->getDashboardData($brand, 30);

        // Get detailed daily data
        $dailyData = $dashboardData['daily'] ?? collect();
        $summary = $dashboardData['summary'] ?? [];
        $totals = [
            'clicks' => $dashboardData['total_clicks'] ?? 0,
            'bookings' => $dashboardData['total_bookings'] ?? 0,
            'commission' => $dashboardData['total_commission'] ?? 0,
            'revenue' => $dashboardData['total_revenue'] ?? 0,
            'conversion_rate' => $dashboardData['avg_conversion_rate'] ?? 0,
        ];

        return view('affiliate.index', compact(
            'brand',
            'dailyData',
            'summary',
            'totals'
        ));
    }

    /**
     * Run affiliate data collection manually.
     */
    public function collect(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        try {
            $this->affiliateService->collectForBrand($brand);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Affiliate data collected successfully.',
                ]);
            }
            
            return redirect()->route('affiliate.index')->with('message', 'Affiliate data collected successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }
            
            return redirect()->route('affiliate.index')->with('error', 'Failed to collect affiliate data: ' . $e->getMessage());
        }
    }
}