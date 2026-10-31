<?php

namespace App\Http\Controllers;

use App\Services\Scanner\PageScannerService;
use App\Services\AI\AiGatewayService;
use App\Services\Intelligence\AcquisitionIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Public URL scanner — no auth required.
 *
 * Free-tier entry point for Vumbi AI.
 * Paste a URL → AI visibility report + full customer acquisition strategy.
 *
 * Rate limit: 3 scans per IP per hour (enforced via Cache).
 * Results cached for 2 hours per URL to avoid duplicate AI costs.
 */
class PublicScannerController extends Controller
{
    public function __construct(
        private PageScannerService            $scanner,
        private AiGatewayService              $ai,
        private AcquisitionIntelligenceService $intelligence,
    ) {}

    // ── Landing page ─────────────────────────────────────────────────────────

    public function index()
    {
        return view('public.scanner.index');
    }

    // ── Run scan (POST) ───────────────────────────────────────────────────────

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

        // ── Cache results by URL (2 hours) ────────────────────────────────────
        $cacheKey = 'public_scan_result_v2:' . md5($url);

        $result = Cache::remember($cacheKey, now()->addHours(2), function () use ($url) {
            return $this->runScan($url);
        });

        if (isset($result['error'])) {
            return back()->withErrors(['url' => $result['error']])->withInput();
        }

        $resultKey = 'scan_' . Str::random(12);
        Cache::put("public_scan_display:{$resultKey}", $result, now()->addHours(2));

        return redirect()->route('public.scanner.result', ['key' => $resultKey]);
    }

    // ── Results page (GET) ────────────────────────────────────────────────────

    public function result(Request $request, string $key)
    {
        $result = Cache::get("public_scan_display:{$key}");

        if (!$result) {
            return redirect()->route('public.scanner.index')
                ->withErrors(['url' => 'This report has expired. Please run a new scan.']);
        }

        return view('public.scanner.result', compact('result'));
    }

    // ── Core scan logic ───────────────────────────────────────────────────────

    private function runScan(string $url): array
    {
        try {
            // 1. Fetch and parse the page
            $parsed = $this->scanner->scanPublicUrl($url);

            if (!$parsed) {
                return ['error' => "We couldn't reach that URL. Check it's publicly accessible and try again."];
            }

            // 2. Visibility analysis (what AI sees about this business right now)
            $aiAnalysis = $this->buildVisibilityReport($url, $parsed);

            // 3. Customer acquisition intelligence (the growth strategy)
            $intelligence = $this->intelligence->analyse($parsed, $url);

            return [
                'url'              => $url,
                'domain'           => parse_url($url, PHP_URL_HOST),
                'scanned_at'       => now()->toISOString(),
                'page'             => $parsed,
                'ai'               => $aiAnalysis,
                'visibility_score' => $aiAnalysis['score'] ?? 0,
                'tier'             => $this->scoreTier($aiAnalysis['score'] ?? 0),
                'tier_color'       => $this->tierColor($aiAnalysis['score'] ?? 0),
                'intelligence'     => $intelligence,
            ];

        } catch (\Throwable $e) {
            Log::error('PublicScannerController: scan failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Something went wrong while scanning. Please try again.'];
        }
    }

    // ── Visibility report (what AI sees right now) ────────────────────────────

    private function buildVisibilityReport(string $url, array $parsed): array
    {
        $title       = $parsed['title'] ?? '';
        $description = $parsed['meta_description'] ?? '';
        $h1          = $parsed['h1'] ?? '';
        $topics      = implode(', ', array_slice($parsed['topics'] ?? [], 0, 15));
        $excerpt     = Str::limit(strip_tags($parsed['content'] ?? ''), 600);
        $domain      = parse_url($url, PHP_URL_HOST);
        $wordCount   = $parsed['word_count'] ?? 0;
        $pageType    = $parsed['page_type'] ?? 'website';

        $response = $this->ai->generate([
            'system_prompt'   => 'You are an AI visibility analyst. Analyse a business website and return ONLY valid JSON. No markdown.',
            'user_prompt'     => <<<PROMPT
URL: {$url}
Domain: {$domain}
Title: {$title}
H1: {$h1}
Meta description: {$description}
Page type: {$pageType}
Word count: {$wordCount}
Topics: {$topics}
Content excerpt: {$excerpt}

Return this exact JSON:
{
  "business_summary": "2-sentence description of what this business does based only on what is visible",
  "score": <integer 0-100, AI visibility score — how clearly AI search engines can understand this business>,
  "score_rationale": "1 sentence explaining the score",
  "top_topics": ["topic1", "topic2", "topic3", "topic4", "topic5"],
  "content_gaps": ["gap1", "gap2", "gap3"],
  "content_recommendations": [
    {"priority": "high", "action": "short action title", "detail": "1-sentence explanation"},
    {"priority": "high", "action": "short action title", "detail": "1-sentence explanation"},
    {"priority": "medium", "action": "short action title", "detail": "1-sentence explanation"}
  ],
  "what_ai_sees": "1 sentence: how an AI assistant would describe this business if asked about it"
}
PROMPT,
            'temperature'     => 0.3,
            'max_tokens'      => 1200,
            'response_format' => 'json',
        ]);

        if (!$response['success']) {
            return $this->fallbackVisibility($parsed);
        }

        $content = preg_replace('/^```json\s*/m', '', $response['content']);
        $content = preg_replace('/^```\s*$/m', '', $content);
        $content = trim($content);

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $decoded['score'] = max(0, min(100, (int) ($decoded['score'] ?? 0)));
            return $decoded;
        } catch (\JsonException) {
            return $this->fallbackVisibility($parsed);
        }
    }

    private function fallbackVisibility(array $parsed): array
    {
        $score = min(100, max(10, (int) (($parsed['word_count'] ?? 0) / 10)));

        return [
            'business_summary'       => 'We scanned the page. Connect an AI provider for a full analysis.',
            'score'                  => $score,
            'score_rationale'        => 'Estimated from content volume — AI provider not configured.',
            'top_topics'             => array_slice($parsed['topics'] ?? [], 0, 5) ?: ['No topics detected'],
            'content_gaps'           => ['AI analysis required for gap detection'],
            'content_recommendations'=> [['priority' => 'high', 'action' => 'Connect AI provider', 'detail' => 'Sign up to get full recommendations.']],
            'what_ai_sees'           => 'Insufficient data — AI provider not configured.',
        ];
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

    private function scoreTier(int $score): string
    {
        return match (true) {
            $score >= 70 => 'Strong',
            $score >= 40 => 'Growing',
            $score >= 15 => 'Emerging',
            default      => 'Not visible',
        };
    }

    private function tierColor(int $score): string
    {
        return match (true) {
            $score >= 70 => 'emerald',
            $score >= 40 => 'sky',
            $score >= 15 => 'amber',
            default      => 'rose',
        };
    }
}
