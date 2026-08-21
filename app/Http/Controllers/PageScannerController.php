<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\PageSnapshot;
use App\Models\AiAction;
use App\Jobs\ScanAllPagesJob;
use App\Jobs\ScanPageJob;
use App\Services\Scanner\PageScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PageScannerController extends Controller
{
    protected PageScannerService $scanner;

    public function __construct(PageScannerService $scanner)
    {
        $this->scanner = $scanner;
    }

    /**
     * Display page scanner dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        // Get all snapshots for this brand
        $snapshots = PageSnapshot::where('brand_id', $brand->id)
        ->orderBy('created_at', 'desc')
        ->get();

        // Statistics
        $totalPages = $snapshots->count();
        $completedScans = PageSnapshot::where('brand_id', $brand->id)
        ->where('status', 'completed')
        ->count();
        $failedScans = PageSnapshot::where('brand_id', $brand->id)
        ->where('status', 'failed')
        ->count();
        $pendingScans = PageSnapshot::where('brand_id', $brand->id)
        ->where('status', 'pending')
        ->count();

        // Pages with issues
        $pagesWithIssues = PageSnapshot::where('brand_id', $brand->id)
        ->where('status', 'completed')
        ->where(function ($query) {
            $query->where('word_count', '<', 300)
            ->orWhereNull('meta_description')
            ->orWhereNull('meta_title');
        })
        ->count();

        // Get actions needing scan
        $actionsNeedingScan = AiAction::where('brand_id', $brand->id)
        ->where('status', 'approved')
        ->whereNotNull('target_url')
        ->whereDoesntHave('pageSnapshot')
        ->count();

        // Group by page type
        $pageTypes = PageSnapshot::where('brand_id', $brand->id)
        ->where('status', 'completed')
        ->select('page_type')
        ->selectRaw('COUNT(*) as count')
        ->groupBy('page_type')
        ->get()
        ->pluck('count', 'page_type')
        ->toArray();

        return view('scanner.index', compact(
            'brand',
            'snapshots',
            'totalPages',
            'completedScans',
            'failedScans',
            'pendingScans',
            'pagesWithIssues',
            'actionsNeedingScan',
            'pageTypes'
        ));
    }

    /**
     * Show a specific page snapshot.
     */
    public function show($id)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $snapshot = PageSnapshot::where('brand_id', $brand->id)
        ->with('action')
        ->findOrFail($id);

        return view('scanner.show', compact('snapshot'));
    }

    /**
     * Scan a single page manually.
     */
    public function scan(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Brand not found'], 404);
            }
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $url = $request->input('url');
        $actionId = $request->input('action_id');

        if (!$url) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'URL is required'], 400);
            }
            return redirect()->back()->with('error', 'URL is required.');
        }

        try {
            $action = $actionId ? AiAction::find($actionId) : null;
            ScanPageJob::dispatch($brand, $url, $action);

            $message = 'Page scan queued successfully.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()->route('scanner.index')->with('message', $message);
        } catch (\Exception $e) {
            $error = 'Failed to queue scan: ' . $e->getMessage();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $error,
                ], 500);
            }

            return redirect()->route('scanner.index')->with('error', $error);
        }
    }

    /**
     * Scan all pages for a brand.
     */
    public function scanAll(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Brand not found'], 404);
            }
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $startUrl = $request->input('start_url', $brand->website_url ?? 'https://' . $brand->slug . '.com');
        $depth = (int) $request->input('depth', 2);

        if (!$startUrl) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Start URL is required'], 400);
            }
            return redirect()->back()->with('error', 'Start URL is required. Please set a website URL in brand settings.');
        }

        try {
            // Log before dispatching
            Log::info('Dispatching ScanAllPagesJob from controller', [
                'brand_id' => $brand->id,
                'start_url' => $startUrl,
                'depth' => $depth,
            ]);

            // Dispatch the job
            ScanAllPagesJob::dispatch($brand, $startUrl, $depth);

            $message = 'Full site scan queued successfully.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()->route('scanner.index')->with('message', $message);
        } catch (\Exception $e) {
            $error = 'Failed to queue full scan: ' . $e->getMessage();
            Log::error('Failed to dispatch ScanAllPagesJob', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
                       'trace' => $e->getTraceAsString(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $error,
                ], 500);
            }

            return redirect()->route('scanner.index')->with('error', $error);
        }
    }

    /**
     * Re-scan an existing page.
     */
    public function rescan($id)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $snapshot = PageSnapshot::where('brand_id', $brand->id)->findOrFail($id);

        try {
            $action = $snapshot->action;
            ScanPageJob::dispatch($brand, $snapshot->url, $action);

            return redirect()->route('scanner.show', $id)->with('message', 'Re-scan queued successfully.');
        } catch (\Exception $e) {
            return redirect()->route('scanner.show', $id)->with('error', 'Failed to queue re-scan: ' . $e->getMessage());
        }
    }

    /**
     * Update the URL for a snapshot.
     */
    public function updateUrl(Request $request, $id)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $snapshot = PageSnapshot::where('brand_id', $brand->id)->findOrFail($id);

        $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $newUrl = $request->input('url');
        $oldUrl = $snapshot->url;

        // Check if the new URL already exists for this brand
        $existing = PageSnapshot::where('brand_id', $brand->id)
        ->where('url', $newUrl)
        ->where('id', '!=', $snapshot->id)
        ->first();

        if ($existing) {
            return redirect()->route('scanner.show', $snapshot->id)
            ->with('error', 'A snapshot with this URL already exists. Please use a different URL.');
        }

        try {
            $snapshot->url = $newUrl;
            $snapshot->save();

            Log::info('Snapshot URL updated', [
                'snapshot_id' => $snapshot->id,
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'brand_id' => $brand->id,
                'user_id' => $user->id,
            ]);

            return redirect()->route('scanner.show', $snapshot->id)
            ->with('message', 'URL updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update snapshot URL', [
                'snapshot_id' => $snapshot->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('scanner.show', $snapshot->id)
            ->with('error', 'Failed to update URL: ' . $e->getMessage());
        }
    }
}
