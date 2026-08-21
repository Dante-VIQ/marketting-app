<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\AhrefsBacklink;
use App\Models\AhrefsKeyword;
use App\Models\AhrefsSiteStat;
use App\Services\Ahrefs\AhrefsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AhrefsController extends Controller
{
    protected AhrefsService $ahrefsService;

    public function __construct(AhrefsService $ahrefsService)
    {
        $this->ahrefsService = $ahrefsService;
    }

    /**
     * Display Ahrefs dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        // Get latest site stats
        $latestStats = AhrefsSiteStat::where('brand_id', $brand->id)
            ->orderBy('tracked_date', 'desc')
            ->first();

        // Get site stats history (last 30 days)
        $statsHistory = AhrefsSiteStat::where('brand_id', $brand->id)
            ->where('tracked_date', '>=', Carbon::today()->subDays(30))
            ->orderBy('tracked_date', 'asc')
            ->get();

        // Get backlinks
        $totalBacklinks = AhrefsBacklink::where('brand_id', $brand->id)->count();
        
        // Recent backlinks (last 7 days)
        $recentBacklinks = AhrefsBacklink::where('brand_id', $brand->id)
            ->where('last_seen_at', '>=', Carbon::today()->subDays(7))
            ->orderBy('last_seen_at', 'desc')
            ->limit(20)
            ->get();

        // New backlinks this week
        $newBacklinksCount = AhrefsBacklink::where('brand_id', $brand->id)
            ->where('first_seen_at', '>=', Carbon::today()->subDays(7))
            ->count();

        // Top referring domains
        $topDomains = AhrefsBacklink::where('brand_id', $brand->id)
            ->select('source_domain')
            ->selectRaw('COUNT(*) as total_links, MAX(source_domain_rating) as max_dr')
            ->groupBy('source_domain')
            ->orderBy('total_links', 'desc')
            ->limit(10)
            ->get();

        // Keyword rankings
        $topKeywords = AhrefsKeyword::where('brand_id', $brand->id)
            ->where('tracked_date', Carbon::today())
            ->whereNotNull('position')
            ->orderBy('position')
            ->limit(20)
            ->get();

        $keywordsCount = AhrefsKeyword::where('brand_id', $brand->id)
            ->where('tracked_date', Carbon::today())
            ->count();

        // Keyword history (last 7 days)
        $keywordHistory = $this->getKeywordHistory($brand);

        // Check Ahrefs configuration
        $isConfigured = $this->ahrefsService->isConfigured();

        // Last collection time
        $lastCollection = AhrefsSiteStat::where('brand_id', $brand->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return view('ahrefs.index', compact(
            'brand',
            'latestStats',
            'statsHistory',
            'totalBacklinks',
            'recentBacklinks',
            'newBacklinksCount',
            'topDomains',
            'topKeywords',
            'keywordsCount',
            'keywordHistory',
            'isConfigured',
            'lastCollection'
        ));
    }

    /**
     * Get keyword history for the last 7 days.
     */
    protected function getKeywordHistory($brand): array
    {
        $topKeywords = AhrefsKeyword::where('brand_id', $brand->id)
            ->where('tracked_date', Carbon::today())
            ->orderBy('position')
            ->limit(5)
            ->pluck('keyword')
            ->toArray();

        $history = [];
        foreach ($topKeywords as $keyword) {
            $rankings = AhrefsKeyword::where('brand_id', $brand->id)
                ->where('keyword', $keyword)
                ->orderBy('tracked_date', 'desc')
                ->limit(7)
                ->get()
                ->reverse();

            $history[$keyword] = $rankings->map(function ($item) {
                return [
                    'date' => $item->tracked_date->format('M d'),
                    'position' => $item->position ?? 'N/A',
                ];
            })->toArray();
        }

        return $history;
    }

    /**
     * Collect Ahrefs data manually.
     */
    public function collect(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Brand not found'], 404);
            }
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        try {
            $result = $this->ahrefsService->collectForBrand($brand);
            
            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }

            $message = 'Ahrefs data collected successfully.';
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()->route('ahrefs.index')->with('message', $message);
        } catch (\Exception $e) {
            $error = 'Failed to collect Ahrefs data: ' . $e->getMessage();
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $error,
                ], 500);
            }

            return redirect()->route('ahrefs.index')->with('error', $error);
        }
    }
}