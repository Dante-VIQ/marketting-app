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

        // Audits stuck in 'running' for >15 min are dead jobs — mark them failed
        // so the UI doesn't show an infinite spinner and new audits can be queued.
        AiVisibilityAudit::where('brand_id', $brand->id)
            ->where('status', 'running')
            ->where('created_at', '<=', now()->subMinutes(15))
            ->update([
                'status' => 'failed',
                'error'  => 'Audit timed out — the background job did not complete within 15 minutes.',
            ]);

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
            ->where('created_at', '>', now()->subMinutes(15))
            ->exists();

        return view('visibility.index', compact('brand', 'latestAudit', 'auditHistory', 'running'));
    }

    /**
     * Dispatch a new audit job for the active brand.
     * Rate-limited: 3× per day (one per 8 hours) unless superadmin.
     */
    public function run(Request $request)
    {
        $brand = Auth::user()->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index');
        }

        // Auto-fail any stale running audit before checking the rate limit,
        // so a dead job doesn't permanently block new audit requests.
        AiVisibilityAudit::where('brand_id', $brand->id)
            ->where('status', 'running')
            ->where('created_at', '<=', now()->subMinutes(15))
            ->update([
                'status' => 'failed',
                'error'  => 'Audit timed out — the background job did not complete within 15 minutes.',
            ]);

        // Rate limit: once per hour per brand (manual runs).
        // Only count done audits or actively running ones (started in last 15 min).
        // Failed audits don't count — users should be able to retry after a failure.
        $recentAudit = AiVisibilityAudit::where('brand_id', $brand->id)
            ->where('created_at', '>=', now()->subHour())
            ->where(function ($q) {
                $q->where('status', 'done')
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'running')
                         ->where('created_at', '>', now()->subMinutes(15));
                  });
            })
            ->first();

        if ($recentAudit && !Auth::user()->isSuperAdmin()) {
            $nextRun = $recentAudit->created_at->addHour()->diffForHumans();
            return back()->with('warning', "An audit was already run recently. You can run the next one {$nextRun}.");
        }

        RunAiVisibilityAuditJob::dispatch($brand);

        return back()->with('success', 'AI visibility audit started. Results will appear in a few minutes.');
    }
}
