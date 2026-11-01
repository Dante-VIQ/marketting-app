<?php

namespace App\Http\Controllers;

use App\Jobs\RunPublicScanJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public URL scanner — no auth required.
 *
 * Free-tier entry point for Vumbi AI.
 * Paste a URL → dispatches RunPublicScanJob → polls /scan/status/{key}
 * → renders full AI report when ready.
 *
 * Rate limit: 3 scans per IP per hour (enforced via Cache).
 * Results cached for 2 hours per URL to avoid duplicate AI costs.
 */
class PublicScannerController extends Controller
{
    // ── Landing page ─────────────────────────────────────────────────────────

    public function index()
    {
        return view('public.scanner.index');
    }

    // ── Submit URL (POST /scan) ───────────────────────────────────────────────

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $url = $this->normalizeUrl($validated['url']);
        $ip  = $request->ip();

        // ── Rate limit: 3 scans / IP / hour ──────────────────────────────────
        $rateLimitKey = "public_scan_rate:{$ip}";
        $scanCount    = (int) Cache::get($rateLimitKey, 0);

        if ($scanCount >= 3) {
            return back()->withErrors(['url' => 'You\'ve run 3 scans this hour. Come back in a bit, or sign up for unlimited scans.']);
        }

        Cache::put($rateLimitKey, $scanCount + 1, now()->addHour());

        // ── Check deduplication cache (same URL within 2 hours) ───────────────
        $dedupeKey = 'public_scan_result_v2:' . md5($url);
        $resultKey = 'scan_' . Str::random(12);

        $cached = Cache::get($dedupeKey);
        if ($cached && !isset($cached['error'])) {
            // Already have a fresh result — serve instantly, no job needed
            Cache::put("public_scan_display:{$resultKey}", $cached, now()->addHours(2));
            Cache::put("public_scan_status:{$resultKey}", 'done', now()->addHours(2));
            return redirect()->route('public.scanner.result', ['key' => $resultKey]);
        }

        // ── Dispatch async job and show polling page ──────────────────────────
        Cache::put("public_scan_status:{$resultKey}", 'pending', now()->addHours(2));
        Cache::put("public_scan_url:{$resultKey}", $url, now()->addHours(2));

        RunPublicScanJob::dispatch($url, $resultKey);

        return redirect()->route('public.scanner.result', ['key' => $resultKey]);
    }

    // ── Status endpoint (GET /scan/status/{key}) ──────────────────────────────
    // Polled by Alpine.js every 3 seconds while status is 'pending'.

    public function status(string $key)
    {
        $status = Cache::get("public_scan_status:{$key}");

        if (!$status) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json([
            'status'       => $status, // 'pending' | 'done' | 'error'
            'redirect_url' => $status === 'done'
                ? route('public.scanner.result', ['key' => $key])
                : null,
        ]);
    }

    // ── Result page (GET /scan/result/{key}) ──────────────────────────────────

    public function result(Request $request, string $key)
    {
        $status = Cache::get("public_scan_status:{$key}");

        if (!$status) {
            return redirect()->route('public.scanner.index')
                ->withErrors(['url' => 'This report has expired. Please run a new scan.']);
        }

        // Still processing — show the polling/loading page
        if ($status === 'pending') {
            $url = Cache::get("public_scan_url:{$key}", '');
            return view('public.scanner.loading', [
                'resultKey' => $key,
                'url'       => $url,
            ]);
        }

        // Error state
        $result = Cache::get("public_scan_display:{$key}");
        if ($status === 'error' || isset($result['error'])) {
            $errorMsg = $result['error'] ?? 'Something went wrong. Please try again.';
            return redirect()->route('public.scanner.index')
                ->withErrors(['url' => $errorMsg]);
        }

        if (!$result) {
            return redirect()->route('public.scanner.index')
                ->withErrors(['url' => 'Report not found. Please run a new scan.']);
        }

        return view('public.scanner.result', compact('result'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function normalizeUrl(string $url): string
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        $host  = strtolower($parts['host'] ?? '');
        $host  = preg_replace('/^www\./', '', $host);
        $path  = $parts['path'] ?? '/';

        return 'https://' . $host . $path;
    }
}
