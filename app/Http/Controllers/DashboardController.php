<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\AiBrief;
use App\Models\AiAction;
use App\Models\RevenueLeak;
use App\Models\GuardianAuditLog;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\AffiliateData;
use App\Services\Analytics\DashboardDataService;
use App\Services\AI\AiGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        DashboardDataService $dashboardDataService,
        AiGatewayService $aiGateway
    ) {
        $user = Auth::user();

        if ($request->has('brand')) {
            $brand = Brand::where('slug', $request->brand)->first();
            if ($brand && $user->hasAccessTo($brand)) {
                $user->switchBrand($brand);
            }
        }

        if (!$user->active_brand_id) {
            $firstBrand = $user->brands()->first();
            if ($firstBrand) {
                $user->switchBrand($firstBrand);
                return redirect()->route('dashboard', ['brand' => $firstBrand->slug]);
            }
            return redirect()->route('brands.index')->with('warning', 'Please add a brand first.');
        }

        $activeBrand = $user->activeBrand;

        // Dashboard data
        $dashboardData = $dashboardDataService->getDashboardData($activeBrand);
        $topPages      = $dashboardDataService->getTopPages($activeBrand);

        // Today's brief
        $todayBrief = AiBrief::where('brand_id', $activeBrand->id)
            ->whereDate('brief_date', now()->toDateString())
            ->first();

        // Pending actions count
        $pendingActionsCount = AiAction::where('brand_id', $activeBrand->id)
            ->where('status', 'pending')
            ->count();

        // Recent actions (last 7 days)
        $recentActions = AiAction::where('brand_id', $activeBrand->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        // Open revenue leaks
        $revenueLeaks = RevenueLeak::where('brand_id', $activeBrand->id)
            ->where('status', 'open')
            ->orderBy('estimated_loss', 'desc')
            ->limit(3)
            ->get()
            ->toArray();

        // Affiliate revenue (last 30 days) — the real money signal for Vumbi
        $affiliateRevenue = AffiliateData::where('brand_id', $activeBrand->id)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->sum('commission_earned');

        // AI status
        $aiStatus = [
            'available'  => $aiGateway->isAvailable(),
            'provider'   => $aiGateway->getProvider(),
            'model'      => config("ai.providers." . $aiGateway->getProvider() . ".model", 'N/A'),
            'last_brief' => $todayBrief ? $todayBrief->created_at->diffForHumans() : 'Never',
        ];

        // Guardian stats
        $aiCallsCount = GuardianAuditLog::where('brand_id', $activeBrand->id)
            ->where('event_type', 'brief_generated')
            ->count();

        $totalTokensUsed = GuardianAuditLog::where('brand_id', $activeBrand->id)
            ->sum('tokens_used');

        $avgResponseTime = GuardianAuditLog::where('brand_id', $activeBrand->id)
            ->avg('response_time_ms');

        // Campaign stats
        $activeCampaigns = Campaign::where('brand_id', $activeBrand->id)
            ->where('status', 'active')
            ->count();

        // Lead stats
        $hotLeads = Lead::where('brand_id', $activeBrand->id)
            ->where('score', 'hot')
            ->where('status', '!=', 'won')
            ->where('status', '!=', 'lost')
            ->count();

        // Badge counts
        $pendingCount = AiAction::where('brand_id', $activeBrand->id)
            ->where('status', 'pending')
            ->count();

        $hotLeadsCount = Lead::where('brand_id', $activeBrand->id)
            ->where('score', 'hot')
            ->where('status', '!=', 'won')
            ->where('status', '!=', 'lost')
            ->count();

        return view('dashboard.index', compact(
            'activeBrand',
            'dashboardData',
            'topPages',
            'todayBrief',
            'pendingActionsCount',
            'recentActions',
            'revenueLeaks',
            'affiliateRevenue',    // ✅ was missing
            'aiStatus',
            'aiCallsCount',
            'totalTokensUsed',
            'avgResponseTime',
            'activeCampaigns',
            'hotLeads',
            'pendingCount',
            'hotLeadsCount'
        ));
    }
}