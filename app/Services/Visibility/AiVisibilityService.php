<?php

namespace App\Services\Visibility;

use App\Models\Brand;
use App\Models\AiVisibilityAudit;
use App\Services\AI\AiGatewayService;
use App\Services\Bing\BingWebmasterService;
use App\Services\BrandContextService;
use Illuminate\Support\Facades\Log;

/**
 * Measures how visible a brand is when people ask AI assistants questions in its niche.
 *
 * Strategy
 * --------
 * We ask the configured AI model a set of real buyer questions (the same queries a
 * potential customer might type into ChatGPT or Gemini). We check whether the AI's
 * answer mentions the brand.  This is an *internal* proxy — we're not hitting live
 * ChatGPT or Gemini APIs, we're using our own AI gateway — but it reflects
 * approximately how a well-trained model would respond to those queries.
 *
 * Each question is a "probe". We track hit/miss and collect context snippets.
 * A score (0–100) is computed from the hit rate, weighted by probe priority.
 *
 * We also ask the AI to compare the brand against known competitors and to list
 * the top 3–5 AEO/GEO recommendations.
 */
class AiVisibilityService
{
    // Resolved niche/region/product — populated by buildProbes(), reused by benchmarkCompetitors()
    private string $resolvedNiche   = 'travel';
    private string $resolvedRegion  = 'East Africa';
    private string $resolvedProduct = 'travel';

    private AiGatewayService $ai;

    /** Maximum seconds a single AI call may take inside runProbes(). */
    private const PROBE_TIMEOUT_SECONDS = 30;

    public function __construct(
        private BrandContextService $brandContext,
        private BingWebmasterService $bing,
    ) {
        $this->ai = AiGatewayService::forPurpose(AiGatewayService::PURPOSE_CONTENT);
    }

    /**
     * Strip markdown code-fence wrappers that some models add even when asked
     * for plain JSON.  Handles ```json … ``` and ``` … ```.
     */
    private function stripJsonFences(string $raw): string
    {
        $trimmed = trim($raw);
        if (preg_match('/^```(?:json)?\s*([\s\S]+?)\s*```$/i', $trimmed, $m)) {
            return trim($m[1]);
        }
        return $trimmed;
    }

    /**
     * Decode a JSON string returned by the AI.  Strips markdown fences first.
     * Returns null on failure so callers can handle gracefully.
     */
    private function decodeJson(string $raw): mixed
    {
        $clean = $this->stripJsonFences($raw);
        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('AiVisibilityService: JSON decode failed', [
                'error'   => json_last_error_msg(),
                'snippet' => substr($clean, 0, 200),
            ]);
            return null;
        }
        return $decoded;
    }

    /**
     * Run a full audit for a brand and persist the result.
     */
    public function run(Brand $brand): AiVisibilityAudit
    {
        $audit = AiVisibilityAudit::create([
            'brand_id' => $brand->id,
            'status'   => 'running',
        ]);

        try {
            $context = $this->brandContext->getFullContext($brand);

            // Build probes — if this fails we have nothing useful, so let it throw.
            $probes = $this->buildProbes($brand, $context);

            // Run probes — individual probe failures are handled inside runProbes().
            $results = $this->runProbes($brand, $probes);

            // These steps are best-effort: a failure returns an empty result
            // rather than aborting the entire audit.
            try {
                $competitors = $this->benchmarkCompetitors($brand, $context);
            } catch (\Throwable $e) {
                Log::warning('AiVisibilityService: benchmarkCompetitors failed', ['brand' => $brand->id, 'error' => $e->getMessage()]);
                $competitors = [];
            }

            try {
                $recs = $this->buildRecommendations($brand, $results, $context);
            } catch (\Throwable $e) {
                Log::warning('AiVisibilityService: buildRecommendations failed', ['brand' => $brand->id, 'error' => $e->getMessage()]);
                $recs = [];
            }

            try {
                $summary = $this->buildSummary($brand, $results, $competitors, $recs);
            } catch (\Throwable $e) {
                Log::warning('AiVisibilityService: buildSummary failed', ['brand' => $brand->id, 'error' => $e->getMessage()]);
                $hit_fallback   = count(array_filter($results, fn($r) => $r['mentioned']));
                $total_fallback = count($results);
                $score_fallback = $total_fallback > 0 ? (int) round(($hit_fallback / $total_fallback) * 100) : 0;
                $summary = "Audit complete. Score: {$score_fallback}/100 ({$hit_fallback}/{$total_fallback} probes).";
            }

            $hit   = count(array_filter($results, fn($r) => $r['mentioned']));
            $sent  = count($results);
            $score = $sent > 0 ? (int) round(($hit / $sent) * 100) : 0;

            $audit->update([
                'score'       => $score,
                'probes_sent' => $sent,
                'probes_hit'  => $hit,
                'report'      => [
                    'queries'         => $results,
                    'competitors'     => $competitors,
                    'recommendations' => $recs,
                ],
                'summary'    => $summary,
                'status'     => 'done',
                'audited_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AiVisibilityService failed', [
                'brand' => $brand->id,
                'error' => $e->getMessage(),
            ]);
            $audit->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }

        return $audit->fresh();
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Build context-aware probe questions for this brand's niche.
     * Returns an array of ['query' => string, 'priority' => high|medium] items.
     */
    private function buildProbes(Brand $brand, array $context): array
    {
        $name       = $brand->name;
        $brandVoice = $context['brand_voice'] ?? '';
        $website    = $brand->website_url ?? '';

        // Collect supplementary signals from page scanner if available
        $pageSignals = '';
        if (!empty($context['pages_scanned_count']) && $context['pages_scanned_count'] > 0) {
            $pageTopics = implode(', ', array_slice($context['all_topics'] ?? [], 0, 15));
            $pageTypes  = implode(', ', $context['page_types_found'] ?? []);
            $pageTitles = implode(' | ', array_filter(array_map(
                fn($p) => $p['title'] ?? null,
                array_slice($context['scanned_pages'] ?? [], 0, 5)
            )));
            $pageSignals = "\nScanned pages ({$context['pages_scanned_count']} pages): {$pageTitles}"
                . "\nPage types found: {$pageTypes}"
                . "\nTop topics across all pages: {$pageTopics}";
        }

        // Step 1: Let the AI determine the brand's actual niche and region
        // from its name, website, brand voice, and page scanner data — so we
        // don't rely on domain_type which describes the platform category, not
        // the brand's actual market.
        $nicheRaw = $this->ai->generate([
            'system_prompt'   => 'You are a brand analyst. Return ONLY a JSON object, no markdown.',
            'user_prompt'     => "Based on this information, determine the brand's niche industry and primary region.\n"
                . "Brand name: {$name}\n"
                . "Website: {$website}\n"
                . "Brand description: {$brandVoice}"
                . $pageSignals . "\n\n"
                . "Return ONLY JSON: {\"niche\": \"e.g. travel and safari\", \"region\": \"e.g. East Africa\", \"product\": \"e.g. safari tour\"}",
            'response_format' => 'json',
            'max_tokens'      => 128,
        ]);

        $niche   = $context['domain_type'] ?? 'travel';
        $region  = 'East Africa';
        $product = $niche;

        if ($nicheRaw['success']) {
            $decoded = $this->decodeJson($nicheRaw['content']);
            if (is_array($decoded)) {
                $niche   = $decoded['niche']   ?? $niche;
                $region  = $decoded['region']  ?? $region;
                $product = $decoded['product'] ?? $niche;
            }
        }

        // Step 2: Generate buyer probe questions for this niche
        $aiProbesRaw = $this->ai->generate([
            'system_prompt'   => 'You are an AI visibility analyst. Return ONLY valid JSON arrays, no markdown, no explanations.',
            'user_prompt'     => "Generate 6 realistic questions that a potential customer might ask an AI assistant like ChatGPT or Google Gemini "
                . "when looking for a {$product} company in {$region}. "
                . "These should be natural language questions a real buyer would ask — not keyword searches. "
                . "Do NOT include the brand name '{$name}' in the questions. "
                . "Return ONLY a JSON array of strings. Example: [\"question 1\", \"question 2\"]",
            'response_format' => 'json',
            'max_tokens'      => 512,
        ]);

        $aiProbes = [];
        if ($aiProbesRaw['success']) {
            $decoded = $this->decodeJson($aiProbesRaw['content']);
            if (is_array($decoded)) {
                foreach ($decoded as $q) {
                    if (is_string($q) && strlen($q) > 10) {
                        $aiProbes[] = ['query' => $q, 'priority' => 'medium'];
                    }
                }
            }
        }

        // Always include fixed high-priority probes that directly name the brand
        $fixed = [
            ['query' => "What is {$name} and what do they offer?",                    'priority' => 'high'],
            ['query' => "Best {$product} companies in {$region} — recommendations?",  'priority' => 'high'],
            ['query' => "Who are the top {$product} providers in Africa?",             'priority' => 'high'],
        ];

        // ── Bing real-query probes ─────────────────────────────────────────────
        // Real user queries that reached this site via Bing search are more
        // representative than AI-invented ones. Use them as medium-priority probes
        // to measure brand mention rates on queries users actually type.
        $bingProbes = [];
        try {
            $realQueries = $this->bing->getRealQueries($brand, 8);
            foreach ($realQueries as $q) {
                if (is_string($q) && strlen($q) > 5 && !str_contains(strtolower($q), strtolower($name))) {
                    $bingProbes[] = ['query' => $q, 'priority' => 'medium', 'source' => 'bing'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AiVisibilityService: Bing probes unavailable', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
        }

        // Store resolved niche/region so benchmarkCompetitors can reuse them
        $this->resolvedNiche   = $niche;
        $this->resolvedRegion  = $region;
        $this->resolvedProduct = $product;

        // Merge: fixed first, then real Bing queries (preferred over AI-generated),
        // then AI-generated probes to fill remaining slots up to 12 total.
        $aiProbeSlots = max(0, 6 - count($bingProbes));

        return array_merge(
            $fixed,
            array_slice($bingProbes, 0, 6),
            array_slice($aiProbes, 0, $aiProbeSlots)
        );
    }

    /**
     * Run each probe through the AI and record whether the brand was mentioned.
     *
     * Each probe is wrapped in a try/catch so a single failing or hanging call
     * does not abort the entire audit.  We also enforce a wall-clock guard per
     * probe: if the cumulative time since the probe started exceeds
     * PROBE_TIMEOUT_SECONDS we record a miss and move on rather than hanging
     * the whole job.
     */
    private function runProbes(Brand $brand, array $probes): array
    {
        $results = [];

        foreach ($probes as $probe) {
            $probeStart = microtime(true);

            try {
                $response = $this->ai->generate([
                    'system_prompt' => 'You are a knowledgeable AI assistant. Answer questions accurately, naming real companies and services when relevant.',
                    'user_prompt'   => $probe['query'],
                    'max_tokens'    => 600,
                ]);

                $elapsed = microtime(true) - $probeStart;
                if ($elapsed > self::PROBE_TIMEOUT_SECONDS) {
                    Log::warning('AiVisibilityService: probe timed out', [
                        'brand_id' => $brand->id,
                        'query'    => $probe['query'],
                        'elapsed'  => round($elapsed, 1),
                    ]);
                    $response = ['success' => false];
                }
            } catch (\Throwable $e) {
                Log::warning('AiVisibilityService: probe threw', [
                    'brand_id' => $brand->id,
                    'query'    => $probe['query'],
                    'error'    => $e->getMessage(),
                ]);
                $response = ['success' => false];
            }

            $mentioned = false;
            $context   = '';

            if ($response['success']) {
                $content   = $response['content'];
                $mentioned = $this->mentionsBrand($brand->name, $content);
                $context   = $mentioned ? $this->extractContext($brand->name, $content) : '';
            }

            $results[] = [
                'query'     => $probe['query'],
                'priority'  => $probe['priority'],
                'mentioned' => $mentioned,
                'context'   => $context,
            ];
        }

        return $results;
    }

    /**
     * Ask the AI to compare the brand's estimated visibility vs named competitors.
     */
    private function benchmarkCompetitors(Brand $brand, array $context): array
    {
        // Use the niche/region resolved by buildProbes() — they reflect the brand's actual market
        $niche  = $this->resolvedNiche;
        $region = $this->resolvedRegion;

        $response = $this->ai->generate([
            'system_prompt'   => 'You are an AI visibility analyst. Return ONLY valid JSON arrays, no markdown, no explanations.',
            'user_prompt'     => "In the context of {$niche} in {$region}, list 4 well-known competitors to '{$brand->name}'. "
                . "For each, estimate their AI visibility score (0–100) based on how often they would be mentioned by AI assistants for niche queries. "
                . "Return ONLY a JSON array: [{\"name\": \"CompanyX\", \"score\": 65, \"reason\": \"brief reason\"}]. "
                . "Include '{$brand->name}' as one of the entries.",
            'response_format' => 'json',
            'max_tokens'      => 512,
        ]);

        if (!$response['success']) {
            return [];
        }

        $decoded = $this->decodeJson($response['content']);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Generate prioritised AEO/GEO improvement recommendations.
     *
     * Handles two distinct cases:
     *  - Some probes missed → tell the AI which queries failed so it can give
     *    targeted "get mentioned for X" recommendations.
     *  - All probes hit     → the brand is already well-represented; ask the AI
     *    for proactive recommendations to maintain and expand that visibility.
     */
    private function buildRecommendations(Brand $brand, array $results, array $context): array
    {
        $missedQueries = array_filter($results, fn($r) => !$r['mentioned']);
        $hitQueries    = array_filter($results, fn($r) => $r['mentioned']);

        $totalHit   = count($hitQueries);
        $totalMissed = count($missedQueries);

        if ($totalMissed === 0) {
            // All probes hit — great visibility; give maintenance/expansion advice
            $hitSample = implode("\n- ", array_slice(array_column($hitQueries, 'query'), 0, 5));
            $prompt = "You are an AEO (Answer Engine Optimization) expert. "
                . "Brand: {$brand->name}. Website: " . ($brand->website_url ?? 'none') . ".\n\n"
                . "Great news: this brand was mentioned in ALL {$totalHit} AI probe queries, including:\n- {$hitSample}\n\n"
                . "Give 4 specific, actionable recommendations to maintain and further EXPAND this brand's AI visibility. "
                . "Focus on strategies to deepen authority, reach new query categories, and stay ahead of competitors. "
                . "Each recommendation should be concrete (e.g. 'Publish monthly expert round-ups to build topical authority', not 'create more content'). "
                . "Return ONLY JSON: [{\"priority\": \"high|medium\", \"action\": \"...\", \"reason\": \"...\"}]";
        } else {
            $missedList = implode("\n- ", array_column($missedQueries, 'query'));
            $context_line = $totalHit > 0
                ? "It WAS mentioned in {$totalHit} out of " . count($results) . " probes."
                : "It was not mentioned in any of the " . count($results) . " probes.";

            $prompt = "You are an AEO (Answer Engine Optimization) expert. "
                . "Brand: {$brand->name}. Website: " . ($brand->website_url ?? 'none') . ".\n\n"
                . "{$context_line} It was NOT mentioned when AI assistants were asked:\n- {$missedList}\n\n"
                . "Give 4 specific, actionable recommendations to improve this brand's AI visibility for those missed queries. "
                . "Each should be concrete (e.g. 'Add FAQ schema markup to your homepage', not 'improve content'). "
                . "Return ONLY JSON: [{\"priority\": \"high|medium\", \"action\": \"...\", \"reason\": \"...\"}]";
        }

        $response = $this->ai->generate([
            'system_prompt'   => 'You are an AEO expert. Return ONLY valid JSON arrays, no markdown, no explanations.',
            'user_prompt'     => $prompt,
            'response_format' => 'json',
            'max_tokens'      => 800,
        ]);

        if (!$response['success']) {
            return [];
        }

        $decoded = $this->decodeJson($response['content']);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Synthesise a one-paragraph human-readable summary.
     */
    private function buildSummary(Brand $brand, array $results, array $competitors, array $recs): string
    {
        $hit   = count(array_filter($results, fn($r) => $r['mentioned']));
        $total = count($results);
        $score = $total > 0 ? (int) round(($hit / $total) * 100) : 0;

        $topRec = !empty($recs) ? $recs[0]['action'] : 'improve structured content';

        $response = $this->ai->generate([
            'system_prompt' => 'You are a marketing analyst. Write concise, direct prose. No bullet points.',
            'user_prompt'   => "Write one paragraph (3–4 sentences) summarising the AI visibility audit for '{$brand->name}'. "
                . "Key facts: score {$score}/100, mentioned in {$hit} of {$total} probe queries. "
                . "Top recommendation: {$topRec}. "
                . "Be direct, specific, and practical.",
            'max_tokens'    => 300,
        ]);

        return $response['success'] ? $response['content'] : "Audit complete. Score: {$score}/100 ({$hit}/{$total} probes).";
    }

    /**
     * Check if a brand name (case-insensitive, partial match) appears in text.
     */
    private function mentionsBrand(string $brandName, string $text): bool
    {
        // Try exact name first
        if (stripos($text, $brandName) !== false) {
            return true;
        }
        // Try first word of brand name (e.g. "Vumbi" from "Vumbi Ventures")
        $firstWord = strtok($brandName, ' ');
        return strlen($firstWord) > 3 && stripos($text, $firstWord) !== false;
    }

    /**
     * Extract a short snippet around the brand mention for context display.
     */
    private function extractContext(string $brandName, string $text): string
    {
        $pos = stripos($text, $brandName);
        if ($pos === false) {
            $pos = stripos($text, strtok($brandName, ' '));
        }
        if ($pos === false) {
            return '';
        }
        $start = max(0, $pos - 100);
        $end   = min(strlen($text), $pos + strlen($brandName) + 150);
        $snippet = substr($text, $start, $end - $start);
        return ($start > 0 ? '…' : '') . trim($snippet) . ($end < strlen($text) ? '…' : '');
    }
}
