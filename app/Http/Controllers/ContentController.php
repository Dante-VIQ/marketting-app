<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateContentForActionJob;
use App\Models\AiAction;
use App\Models\ContentDraft;
use App\Services\AI\ContentGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContentController extends Controller
{
    protected ContentGeneratorService $contentGenerator;

    public function __construct(ContentGeneratorService $contentGenerator)
    {
        $this->contentGenerator = $contentGenerator;
    }

    /**
     * Display content drafts stats and listings.
     */
    public function drafts()
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $totalDrafts = ContentDraft::where('brand_id', $brand->id)->count();
        $pendingDrafts = ContentDraft::where('brand_id', $brand->id)->where('status', 'draft')->count();
        $approvedDrafts = ContentDraft::where('brand_id', $brand->id)->where('status', 'approved')->count();
        $publishedDrafts = ContentDraft::where('brand_id', $brand->id)->where('status', 'published')->count();

        $approvedActionsWithoutDrafts = AiAction::where('brand_id', $brand->id)
            ->where('status', 'approved')
            ->whereDoesntHave('contentDraft')
            ->count();

        return view('content.drafts', compact(
            'totalDrafts',
            'pendingDrafts',
            'approvedDrafts',
            'publishedDrafts',
            'approvedActionsWithoutDrafts'
        ));
    }

    /**
     * Dispatch batch content generation.
     */
    public function generateAll(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Brand not found'], 404);
            }
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        // Fetch unassigned approved actions
        $actions = AiAction::where('brand_id', $brand->id)
            ->where('status', 'approved')
            ->whereDoesntHave('contentDraft')
            ->get();

        if ($actions->isEmpty()) {
            $msg = 'No approved actions waiting for content generation.';
            return $request->expectsJson()
                ? response()->json(['success' => true, 'message' => $msg, 'dispatched' => 0])
                : redirect()->route('content.drafts')->with('message', $msg);
        }

        // Dispatch asynchronous queue jobs to prevent gateway timeout
        $dispatchedCount = 0;
        foreach ($actions as $action) {
            GenerateContentForActionJob::dispatch($action);
            $dispatchedCount++;
        }

        $message = "Queued {$dispatchedCount} actions for generation. Content drafts will populate shortly.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'dispatched' => $dispatchedCount,
            ]);
        }

        return redirect()->route('content.drafts')->with('message', $message);
    }

    /**
     * Check queue status for the dashboard.
     */
    public function queueStatus(): JsonResponse
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        $pending = AiAction::where('brand_id', $brand->id)
            ->where('status', 'approved')
            ->whereDoesntHave('contentDraft')
            ->count();

        $processing = AiAction::where('brand_id', $brand->id)
            ->where('status', 'content_generated')
            ->count();

        return response()->json([
            'pending' => $pending,
            'processing' => $processing,
            'completed' => ContentDraft::where('brand_id', $brand->id)->count(),
        ]);
    }
}