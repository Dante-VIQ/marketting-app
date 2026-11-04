<?php

namespace App\Jobs;

use App\Http\Controllers\PublicScannerController;
use App\Services\AI\AiGatewayService;
use App\Services\Intelligence\AcquisitionIntelligenceService;
use App\Services\Scanner\PageScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs the full public URL scan pipeline asynchronously.
 *
 * Fires up to 4 AI calls + 5 Tavily searches — too expensive for a
 * synchronous web request. The controller dispatches this job, stores a
 * "pending" marker in cache, and the client polls /scan/status/{key}
 * until the result is ready.
 *
 * Cache keys:
 *   public_scan_status:{key}       → 'pending' | 'done' | 'error'
 *   public_scan_display:{key}      → the full result array (set when done)
 *   public_scan_result_v2:{md5}    → deduplicated result keyed by URL (2hr)
 */
class RunPublicScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Single attempt — AI calls are expensive; retrying a failed run doubles cost. */
    public int $tries = 1;

    /** Allow up to 3 minutes — 4 AI calls + Tavily can take ~90s under load. */
    public int $timeout = 180;

    public function __construct(
        private string $url,
        private string $resultKey,
    ) {}

    public function handle(
        PageScannerService             $scanner,
        AcquisitionIntelligenceService $intelligence,
    ): void {
        // Use the scan-specific provider so this job doesn't exhaust the quota
        // used by brief generation or chat. Set AI_PROVIDER_SCAN in .env.
        $ai = AiGatewayService::forPurpose(AiGatewayService::PURPOSE_SCAN);

        // Status/display keys use the file store so the polling page can always
        // read them regardless of default DB cache health.
        $store = PublicScannerController::statusStore();

        try {
            // Deduplicated cache: same URL within 2 hours returns the stored result
            $dedupeKey = 'public_scan_result_v2:' . md5($this->url);

            $result = Cache::remember($dedupeKey, now()->addHours(2), function () use ($scanner, $ai, $intelligence) {
                return $this->runScan($scanner, $ai, $intelligence);
            });

            if (isset($result['error'])) {
                $store->put("public_scan_status:{$this->resultKey}", 'error',  now()->addHours(2));
                $store->put("public_scan_display:{$this->resultKey}", $result, now()->addHours(2));
                return;
            }

            $store->put("public_scan_display:{$this->resultKey}", $result, now()->addHours(2));
            $store->put("public_scan_status:{$this->resultKey}",  'done',  now()->addHours(2));

        } catch (\Throwable $e) {
            Log::error('RunPublicScanJob: failed', ['url' => $this->url, 'error' => $e->getMessage()]);
            $store->put("public_scan_status:{$this->resultKey}",  'error',                                    now()->addHour());
            $store->put("public_scan_display:{$this->resultKey}", ['error' => 'Scan failed. Please try again.'], now()->addHour());
        }
    }

    private function runScan(
        PageScannerService             $scanner,
        AiGatewayService               $ai,
        AcquisitionIntelligenceService $intelligence,
    ): array {
        $parsed = $scanner->scanPublicUrl($this->url);

        if (!$parsed) {
            return ['error' => "We couldn't reach that URL. Check it's publicly accessible and try again."];
        }

        // Run visibility report and acquisition intelligence
        $aiAnalysis   = $this->buildVisibilityReport($ai, $parsed);
        $intel        = $intelligence->analyse($parsed, $this->url);

        return [
            'url'              => $this->url,
            'domain'           => parse_url($this->url, PHP_URL_HOST),
            'scanned_at'       => now()->toISOString(),
            'page'             => $parsed,
            'ai'               => $aiAnalysis,
            'visibility_score' => $aiAnalysis['score'] ?? 0,
            'tier'             => $this->scoreTier($aiAnalysis['score'] ?? 0),
            'tier_color'       => $this->tierColor($aiAnalysis['score'] ?? 0),
            'intelligence'     => $intel,
        ];
    }

    private function buildVisibilityReport(AiGatewayService $ai, array $parsed): array
    {
        $title       = $parsed['title'] ?? '';
        $description = $parsed['meta_description'] ?? '';
        $h1          = $parsed['h1'] ?? '';
        $topics      = implode(', ', array_slice($parsed['topics'] ?? [], 0, 15));
        $excerpt     = Str::limit(strip_tags($parsed['content'] ?? ''), 600);
        $domain      = parse_url($this->url, PHP_URL_HOST);
        $wordCount   = $parsed['word_count'] ?? 0;
        $pageType    = $parsed['page_type'] ?? 'website';

        $response = $ai->generate([
            'system_prompt'   => 'You are an AI visibility analyst. Return ONLY valid JSON. No markdown.',
            'user_prompt'     => <<<PROMPT
URL: {$this->url}
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
  "business_summary": "2-sentence description based only on what is visible",
  "score": <integer 0-100, AI visibility score>,
  "score_rationale": "1 sentence explaining the score",
  "top_topics": ["topic1", "topic2", "topic3", "topic4", "topic5"],
  "content_gaps": ["gap1", "gap2", "gap3"],
  "content_recommendations": [
    {"priority": "high", "action": "short action title", "detail": "1-sentence explanation"},
    {"priority": "high", "action": "short action title", "detail": "1-sentence explanation"},
    {"priority": "medium", "action": "short action title", "detail": "1-sentence explanation"}
  ],
  "what_ai_sees": "1 sentence: how an AI assistant would describe this business"
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
            $decoded          = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
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
            'business_summary'        => 'We scanned the page. Connect an AI provider for a full analysis.',
            'score'                   => $score,
            'score_rationale'         => 'Estimated from content volume — AI provider not configured.',
            'top_topics'              => array_slice($parsed['topics'] ?? [], 0, 5) ?: ['No topics detected'],
            'content_gaps'            => ['AI analysis required for gap detection'],
            'content_recommendations' => [['priority' => 'high', 'action' => 'Connect AI provider', 'detail' => 'Sign up to get full recommendations.']],
            'what_ai_sees'            => 'Insufficient data — AI provider not configured.',
        ];
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
