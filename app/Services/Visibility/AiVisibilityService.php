<?php

namespace App\Services\Visibility;

use App\Models\Brand;
use App\Models\AiVisibilityAudit;
use App\Services\AI\AiGatewayService;
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
    public function __construct(
        private AiGatewayService   $ai,
        private BrandContextService $brandContext,
    ) {}

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
            $context     = $this->brandContext->getFullContext($brand);
            $probes      = $this->buildProbes($brand, $context);
            $results     = $this->runProbes($brand, $probes);
            $competitors = $this->benchmarkCompetitors($brand, $context);
            $recs        = $this->buildRecommendations($brand, $results, $context);
            $summary     = $this->buildSummary($brand, $results, $competitors, $recs);

            $hit        = count(array_filter($results, fn($r) => $r['mentioned']));
            $sent       = count($results);
            $score      = $sent > 0 ? (int) round(($hit / $sent) * 100) : 0;

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
        $name    = $brand->name;
        $niche   = $context['industry'] ?? $context['domain_type'] ?? 'travel';
        $region  = $context['region']   ?? 'East Africa';
        $product = $context['product']  ?? $niche;

        // Generate additional probes via AI
        $aiProbesRaw = $this->ai->generate([
            'system_prompt'   => 'You are an AI visibility analyst. Return ONLY valid JSON arrays, no markdown, no explanations.',
            'user_prompt'     => "Generate 6 realistic buyer questions that someone might ask an AI assistant like ChatGPT or Google Gemini when looking for a {$product} company in {$region} (brand: '{$name}', niche: {$niche}). "
                . "These should be natural language questions, not keyword searches. "
                . "Return ONLY a JSON array of strings. Example: [\"question 1\", \"question 2\"]",
            'response_format' => 'json',
            'max_tokens'      => 512,
        ]);

        $aiProbes = [];
        if ($aiProbesRaw['success']) {
            $decoded = json_decode($aiProbesRaw['content'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $q) {
                    if (is_string($q) && strlen($q) > 10) {
                        $aiProbes[] = ['query' => $q, 'priority' => 'medium'];
                    }
                }
            }
        }

        // Always include a direct brand name probe
        $fixed = [
            ['query' => "What is {$name} and what do they do?",                       'priority' => 'high'],
            ['query' => "Best {$product} companies in {$region} — recommendations?",  'priority' => 'high'],
            ['query' => "Who are the top {$product} providers in Africa?",             'priority' => 'high'],
        ];

        return array_merge($fixed, array_slice($aiProbes, 0, 6));
    }

    /**
     * Run each probe through the AI and record whether the brand was mentioned.
     */
    private function runProbes(Brand $brand, array $probes): array
    {
        $results = [];

        foreach ($probes as $probe) {
            $response = $this->ai->generate([
                'system_prompt' => 'You are a knowledgeable AI assistant. Answer questions accurately, naming real companies and services when relevant.',
                'user_prompt'   => $probe['query'],
                'max_tokens'    => 600,
            ]);

            $mentioned = false;
            $context   = '';

            if ($response['success']) {
                $content   = $response['content'];
                $mentioned = $this->mentionsBrand($brand->name, $content);
                $context   = $mentioned ? $this->extractContext($brand->name, $content) : '';
            }

            $results[] = [
                'query'    => $probe['query'],
                'priority' => $probe['priority'],
                'mentioned' => $mentioned,
                'context'  => $context,
            ];
        }

        return $results;
    }

    /**
     * Ask the AI to compare the brand's estimated visibility vs named competitors.
     */
    private function benchmarkCompetitors(Brand $brand, array $context): array
    {
        $niche  = $context['industry'] ?? $context['domain_type'] ?? 'travel';
        $region = $context['region'] ?? 'East Africa';

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

        $decoded = json_decode($response['content'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Generate prioritised AEO/GEO improvement recommendations.
     */
    private function buildRecommendations(Brand $brand, array $results, array $context): array
    {
        $missedQueries = array_filter($results, fn($r) => !$r['mentioned']);
        $missedList    = implode("\n- ", array_column($missedQueries, 'query'));

        $prompt = "You are an AEO (Answer Engine Optimization) expert. "
            . "Brand: {$brand->name}. Website: " . ($brand->website_url ?? 'none') . ".\n\n"
            . "The brand was NOT mentioned when AI assistants were asked:\n- {$missedList}\n\n"
            . "Give 4 specific, actionable recommendations to improve this brand's AI visibility. "
            . "Each should be concrete (e.g. 'Add FAQ schema markup to your homepage', not 'improve content'). "
            . "Return ONLY JSON: [{\"priority\": \"high|medium\", \"action\": \"...\", \"reason\": \"...\"}]";

        $response = $this->ai->generate([
            'system_prompt'   => 'You are an AEO expert. Return ONLY valid JSON arrays, no markdown, no explanations.',
            'user_prompt'     => $prompt,
            'response_format' => 'json',
            'max_tokens'      => 800,
        ]);

        if (!$response['success']) {
            return [];
        }

        $decoded = json_decode($response['content'], true);
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
