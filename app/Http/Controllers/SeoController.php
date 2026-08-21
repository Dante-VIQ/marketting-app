<?php

namespace App\Http\Controllers;

use App\Models\AiAction;
use App\Models\Brand;
use App\Models\KeywordRanking;
use App\Models\SeoIssue;
use App\Services\AI\SeoAssistantService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class SeoController extends Controller
{
    protected SeoAssistantService $seoService;

    public function __construct(SeoAssistantService $seoService)
    {
        $this->seoService = $seoService;
    }

    /**
     * Display the SEO dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        // Get SEO issues
        $issues = SeoIssue::where('brand_id', $brand->id)
            ->where('status', 'open')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->get();

        // Get resolved issues
        $resolvedIssues = SeoIssue::where('brand_id', $brand->id)
            ->where('status', 'resolved')
            ->orderBy('resolved_at', 'desc')
            ->limit(10)
            ->get();

        // Get keyword rankings
        $keywords = KeywordRanking::where('brand_id', $brand->id)
            ->whereDate('tracked_date', Carbon::today())
            ->orderBy('position')
            ->limit(20)
            ->get();

        // Get keyword history for chart (last 7 days)
        $keywordHistory = $this->getKeywordHistory($brand);

        // Get SEO actions
        $seoActions = AiAction::where('brand_id', $brand->id)
            ->where('category', 'seo')
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->get();

        // Get statistics
        $stats = $this->getStats($brand);

        // Generate SEO report
        $report = $this->seoService->generateSeoReport($brand);

        return view('seo.index', compact(
            'brand',
            'issues',
            'resolvedIssues',
            'keywords',
            'keywordHistory',
            'seoActions',
            'stats',
            'report'
        ));
    }

    /**
     * Get keyword ranking history for chart.
     */
    protected function getKeywordHistory($brand): array
    {
        $topKeywords = KeywordRanking::where('brand_id', $brand->id)
            ->select('keyword')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('keyword')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->pluck('keyword')
            ->toArray();

        $history = [];
        foreach ($topKeywords as $keyword) {
            $rankings = KeywordRanking::where('brand_id', $brand->id)
                ->where('keyword', $keyword)
                ->orderBy('tracked_date', 'desc')
                ->limit(7)
                ->get()
                ->reverse();

            $history[$keyword] = $rankings->map(function ($item) {
                return [
                    'date' => $item->tracked_date->format('M d'),
                    'position' => $item->position,
                ];
            })->toArray();
        }

        return $history;
    }

    /**
     * Get SEO statistics.
     */
    protected function getStats($brand): array
    {
        $totalIssues = SeoIssue::where('brand_id', $brand->id)->count();
        $openIssues = SeoIssue::where('brand_id', $brand->id)->where('status', 'open')->count();
        $resolvedIssues = SeoIssue::where('brand_id', $brand->id)->where('status', 'resolved')->count();
        $criticalIssues = SeoIssue::where('brand_id', $brand->id)
            ->where('status', 'open')
            ->where('severity', 'critical')
            ->count();

        $totalKeywords = KeywordRanking::where('brand_id', $brand->id)
            ->whereDate('tracked_date', Carbon::today())
            ->count();

        $avgPosition = KeywordRanking::where('brand_id', $brand->id)
            ->whereDate('tracked_date', Carbon::today())
            ->avg('position') ?? 0;

        return [
            'total_issues' => $totalIssues,
            'open_issues' => $openIssues,
            'resolved_issues' => $resolvedIssues,
            'critical_issues' => $criticalIssues,
            'total_keywords' => $totalKeywords,
            'avg_position' => round($avgPosition, 1),
        ];
    }

    /**
     * Show a specific SEO issue.
     */
    public function showIssue($id)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $issue = SeoIssue::where('brand_id', $brand->id)->findOrFail($id);

        return view('seo.show-issue', compact('issue'));
    }

    /**
     * Mark an SEO issue as resolved.
     */
    public function resolveIssue(Request $request, $id)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        $issue = SeoIssue::where('brand_id', $brand->id)->findOrFail($id);
        $issue->status = 'resolved';
        $issue->resolved_at = now();
        $issue->save();

        // Log the resolution
        \App\Models\GuardianAuditLog::create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'fingerprint' => 'seo_resolved_' . $issue->id,
            'event_type' => 'seo_issue_resolved',
            'metadata' => [
                'issue_id' => $issue->id,
                'page_url' => $issue->page_url,
                'type' => $issue->type,
            ],
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('seo.index')->with('message', 'SEO issue resolved successfully.');
    }

/**
 * Run SEO checks manually.
 */
public function runChecks()
{
    $user = Auth::user();
    $brand = $user->activeBrand;

    if (!$brand) {
        if (request()->ajax()) {
            return response()->json(['success' => false, 'message' => 'Brand not found']);
        }
        return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
    }

    try {
        $this->seoService->performDailyChecks($brand);
        
        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'SEO checks completed successfully.',
                'issues_found' => SeoIssue::where('brand_id', $brand->id)
                    ->where('status', 'open')
                    ->count()
            ]);
        }
        
        return redirect()->url('/seo/index')->with('message', 'SEO checks completed successfully.');
    } catch (\Exception $e) {
        if (request()->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        return redirect()->route('seo.index')->with('error', 'SEO checks failed: ' . $e->getMessage());
    }
}
}