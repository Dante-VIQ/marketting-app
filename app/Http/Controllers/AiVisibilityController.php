<?php

namespace App\Http\Controllers;

use App\Jobs\RunAiVisibilityAuditJob;
use App\Models\AiVisibilityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiVisibilityController extends Controller
{
    /**
     * Show the latest audit for the active brand, or an empty state.
     */
    public function index()
    {
        $brand = Auth::user()->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please add a brand first.');
        }

        $latestAudit  = AiVisibilityAudit::where('brand_id', $brand->id)
            ->orderByDesc('created_at')
            ->first();

        $auditHistory = AiVisibilityAudit::where('brand_id', $brand->id)
            ->where('status', 'done')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $running = AiVisibilityAudit::where('brand_id', $brand->id)
            ->where('status', 'running')
            ->exists();

        return view('visibility.index', compact('brand', 'latestAudit', 'auditHistory', 'running'));
    }

    /**
     * Dispatch a new audit job for the active brand.
     * Rate-limited: one per 24 hours unless superadmin.
     */
    public function run(Request $request)
    {
        $brand = Auth::user()->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index');
        }

        // Prevent hammering the AI
        $recentAudit = AiVisibilityAudit::where('brand_id', $brand->id)
            ->where('created_at', '>=', now()->subHours(23))
            ->whereIn('status', ['running', 'done'])
            ->first();

        if ($recentAudit && !Auth::user()->hasRole('superadmin')) {
            return back()->with('warning', 'An audit was already run recently. You can run one audit per 24 hours.');
        }

        RunAiVisibilityAuditJob::dispatch($brand);

        return back()->with('success', 'AI visibility audit started. Results will appear in a few minutes.');
    }
}
