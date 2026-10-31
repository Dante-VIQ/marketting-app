<?php

namespace App\Services\Intelligence;

use App\Services\AI\AiGatewayService;
use App\Services\Competitors\Discovery\TavilySearch;
use Illuminate\Support\Facades\Log;

/**
 * Customer acquisition intelligence engine.
 *
 * Given a scanned page, produces a full customer acquisition strategy:
 *
 *  1. Business profile  — who this business is, who their customer is,
 *                         how long the buying journey takes.
 *  2. Market research   — Tavily searches synthesised with citations;
 *                         how customers in this category find and choose.
 *  3. Strategy report   — channel recommendations (evidence-backed, confidence-
 *                         labelled), lead funnel, KPIs to track.
 *
 * Every claim is tagged with a reliability label:
 *   verified   — taken directly from the scanned page
 *   research   — from a cited external source
 *   inference  — AI reasoning (no external citation)
 */
class AcquisitionIntelligenceService
{
    /** Max Tavily credits to spend per scan (basic = 1 credit each). */
    private const MAX_SEARCHES = 5;

    public function __construct(
        private AiGatewayService $ai,
        private TavilySearch     $tavily,
    ) {}

    /**
     * Run the full intelligence pipeline.
     *
     * @param  array  $scannedPage  Output of PageScannerService::scanPublicUrl()
     * @param  string $url
     * @return array{
     *   business_profile: array,
     *   market_research: array,
     *   acquisition_strategy: array,
     *   research_available: bool,
     *   error: string|null
     * }
     */
    public function analyse(array $scannedPage, string $url): array
    {
        try {
            // Step 1 — profile the business
            $profile = $this->buildBusinessProfile($scannedPage, $url);

            // Step 2 — research the market (requires Tavily)
            $research = $this->researchMarket($profile);

            // Step 3 — build the acquisition strategy
            $strategy = $this->buildStrategy($scannedPage, $profile, $research);

            return [
                'business_profile'    => $profile,
                'market_research'     => $research,
                'acquisition_strategy'=> $strategy,
                'research_available'  => !empty($research['sources']),
                'error'               => null,
            ];

        } catch (\Throwable $e) {
            Log::error('AcquisitionIntelligenceService: pipeline failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'business_profile'    => [],
                'market_research'     => [],
                'acquisition_strategy'=> [],
                'research_available'  => false,
                'error'               => $e->getMessage(),
            ];
        }
    }

    // ── Step 1: Business profiler ─────────────────────────────────────────────

    private function buildBusinessProfile(array $page, string $url): array
    {
        $title       = $page['title'] ?? '';
        $h1          = $page['h1'] ?? '';
        $description = $page['meta_description'] ?? '';
        $topics      = implode(', ', array_slice($page['topics'] ?? [], 0, 12));
        $excerpt     = \Illuminate\Support\Str::limit(strip_tags($page['content'] ?? ''), 800);
        $domain      = parse_url($url, PHP_URL_HOST);
        $pageType    = $page['page_type'] ?? 'website';

        $response = $this->ai->generate([
            'system_prompt' => 'You are a business analyst. Analyse a website and return structured JSON only. No markdown.',
            'user_prompt'   => <<<PROMPT
Analyse this website and identify the business and its customers.

URL: {$url}
Domain: {$domain}
Page type: {$pageType}
Title: {$title}
H1: {$h1}
Meta description: {$description}
Topics: {$topics}
Content excerpt: {$excerpt}

Return this exact JSON:
{
  "business_type": "one clear category (e.g. travel agency, accounting firm, fashion retailer, SaaS tool)",
  "industry": "broad industry (e.g. travel, finance, fashion, technology)",
  "niche": "specific niche within the industry",
  "geography": "apparent target geography based on the site (e.g. Kenya, East Africa, global)",
  "target_customer": "describe the most likely buyer in 1-2 sentences",
  "customer_segments": ["segment1", "segment2"],
  "value_proposition": "what this business offers in plain language",
  "price_point": "budget | mid-market | premium | unknown",
  "buying_journey": "impulse | short (days) | medium (weeks) | long (months)",
  "buying_journey_rationale": "1 sentence explaining why",
  "decision_triggers": ["trigger1", "trigger2", "trigger3"],
  "search_intent": "what would a customer type into Google when looking for this business",
  "confidence": "high | medium | low",
  "confidence_rationale": "why you are or aren't confident about this profile"
}
PROMPT,
            'temperature'     => 0.2,
            'max_tokens'      => 800,
            'response_format' => 'json',
        ]);

        if (!$response['success']) {
            return ['business_type' => 'unknown', 'industry' => 'unknown', 'error' => $response['error'] ?? 'Profile failed'];
        }

        return $this->parseJson($response['content'], [
            'business_type' => 'unknown',
            'industry'      => 'unknown',
        ]);
    }

    // ── Step 2: Market researcher ─────────────────────────────────────────────

    private function researchMarket(array $profile): array
    {
        if ($profile['business_type'] === 'unknown') {
            return ['summary' => 'Could not identify business type.', 'sources' => [], 'findings' => []];
        }

        $type      = $profile['business_type'] ?? 'business';
        $industry  = $profile['industry'] ?? 'business';
        $niche     = $profile['niche'] ?? $type;
        $geo       = $profile['geography'] ?? '';
        $geoSuffix = $geo && strtolower($geo) !== 'global' ? " in {$geo}" : '';

        // Build targeted search queries
        $queries = array_slice(array_filter([
            "how customers find and choose {$niche}{$geoSuffix}",
            "most effective marketing channels for {$type}{$geoSuffix} 2024",
            "{$industry} customer acquisition statistics and research",
            "lead generation strategies for {$niche}",
            $geo ? "digital marketing {$industry} {$geo}" : null,
        ]), 0, self::MAX_SEARCHES);

        $allResults = [];
        $sources    = [];

        if ($this->tavily->configured()) {
            foreach ($queries as $query) {
                try {
                    $results = $this->tavily->search($query, 3);
                    foreach ($results as $r) {
                        $allResults[] = $r;
                        $sources[]    = ['title' => $r['title'], 'url' => $r['url']];
                    }
                } catch (\Throwable $e) {
                    Log::warning('AcquisitionIntelligenceService: Tavily search failed', [
                        'query' => $query,
                        'error' => $e->getMessage(),
                    ]);
                    // continue — partial research is better than none
                }
            }
        }

        // Deduplicate sources by URL
        $sources = array_values(array_unique($sources, SORT_REGULAR));

        if (empty($allResults)) {
            // No Tavily or no results — fall back to pure AI inference, clearly labelled
            return $this->inferMarketWithoutResearch($profile);
        }

        // Synthesise research with AI
        return $this->synthesiseResearch($profile, $allResults, $sources);
    }

    private function synthesiseResearch(array $profile, array $results, array $sources): array
    {
        $type     = $profile['business_type'] ?? 'business';
        $industry = $profile['industry'] ?? 'business';
        $geo      = $profile['geography'] ?? '';

        $researchText = '';
        foreach (array_slice($results, 0, 12) as $i => $r) {
            $researchText .= "\n[{$i}] {$r['title']}\n{$r['url']}\n{$r['snippet']}\n";
        }

        $response = $this->ai->generate([
            'system_prompt' => 'You are a market research analyst. Synthesise the provided research into structured JSON. Cite sources by their index [0], [1], etc. No markdown.',
            'user_prompt'   => <<<PROMPT
Business type: {$type}
Industry: {$industry}
Geography: {$geo}

Research results:
{$researchText}

Synthesise the research and return this exact JSON:
{
  "how_customers_discover": "2-3 sentences on how customers find businesses like this, citing sources by [index]",
  "how_customers_decide": "2-3 sentences on what makes customers choose one over another, citing sources",
  "top_channels": [
    {
      "channel": "channel name",
      "why_it_works": "1-2 sentences with source citation",
      "evidence_type": "research | inference",
      "source_indices": [0, 1]
    }
  ],
  "market_context": "1-2 sentences on market conditions relevant to this business type",
  "data_gaps": ["what we couldn't find in the research"],
  "research_quality": "strong | moderate | limited"
}
PROMPT,
            'temperature'     => 0.3,
            'max_tokens'      => 1000,
            'response_format' => 'json',
        ]);

        $parsed = $response['success']
            ? $this->parseJson($response['content'], [])
            : [];

        return array_merge($parsed, ['sources' => $sources]);
    }

    private function inferMarketWithoutResearch(array $profile): array
    {
        $type    = $profile['business_type'] ?? 'business';
        $geo     = $profile['geography'] ?? '';

        $response = $this->ai->generate([
            'system_prompt' => 'You are a marketing strategist. Return JSON only. No markdown.',
            'user_prompt'   => <<<PROMPT
Without external research, use your knowledge to describe how customers typically find and choose a {$type} in {$geo}.

Return this exact JSON:
{
  "how_customers_discover": "2-3 sentences on typical discovery channels",
  "how_customers_decide": "2-3 sentences on typical decision factors",
  "top_channels": [
    {"channel": "channel name", "why_it_works": "brief rationale", "evidence_type": "inference", "source_indices": []}
  ],
  "market_context": "1-2 sentences of market context",
  "data_gaps": ["No external research available — all findings are AI inference"],
  "research_quality": "inference-only"
}
PROMPT,
            'temperature'     => 0.4,
            'max_tokens'      => 700,
            'response_format' => 'json',
        ]);

        $parsed = $response['success']
            ? $this->parseJson($response['content'], [])
            : [];

        return array_merge($parsed, ['sources' => []]);
    }

    // ── Step 3: Strategy builder ──────────────────────────────────────────────

    private function buildStrategy(array $page, array $profile, array $research): array
    {
        $type       = $profile['business_type'] ?? 'business';
        $customer   = $profile['target_customer'] ?? '';
        $journey    = $profile['buying_journey'] ?? 'medium (weeks)';
        $triggers   = implode(', ', $profile['decision_triggers'] ?? []);
        $channels   = implode('; ', array_map(
            fn($c) => ($c['channel'] ?? '') . ' — ' . ($c['why_it_works'] ?? ''),
            array_slice($research['top_channels'] ?? [], 0, 5)
        ));
        $howDiscover = $research['how_customers_discover'] ?? '';
        $howDecide   = $research['how_customers_decide'] ?? '';
        $topics      = implode(', ', array_slice($page['topics'] ?? [], 0, 8));
        $pageStrengths = $page['word_count'] ?? 0 > 500 ? 'substantial content' : 'thin content';

        $response = $this->ai->generate([
            'system_prompt' => 'You are a growth strategist. Build a specific, actionable customer acquisition plan. Return JSON only. No markdown.',
            'user_prompt'   => <<<PROMPT
Build a customer acquisition strategy for this business.

Business type: {$type}
Target customer: {$customer}
Buying journey: {$journey}
Decision triggers: {$triggers}
Page content: {$pageStrengths}, topics: {$topics}

How customers discover: {$howDiscover}
How customers decide: {$howDecide}
Relevant channels from research: {$channels}

Return this exact JSON:
{
  "headline": "one punchy sentence summarising the growth opportunity",
  "channels": [
    {
      "name": "channel name",
      "role": "awareness | consideration | conversion | retention",
      "recommendation": "specific tactic for this business in 2 sentences",
      "effort": "low | medium | high",
      "impact": "low | medium | high",
      "time_to_results": "e.g. 2-4 weeks, 3-6 months",
      "evidence": "why this applies — cite research or label as inference",
      "evidence_type": "research | inference",
      "priority": 1
    }
  ],
  "lead_funnel": {
    "awareness": {"description": "how strangers find this business", "tactics": ["tactic1", "tactic2"]},
    "interest": {"description": "how interest is captured", "tactics": ["tactic1", "tactic2"]},
    "consideration": {"description": "how leads are nurtured", "tactics": ["tactic1", "tactic2"]},
    "conversion": {"description": "how leads become customers", "tactics": ["tactic1", "tactic2"]},
    "retention": {"description": "how customers come back and refer", "tactics": ["tactic1"]}
  },
  "quick_wins": [
    {"action": "specific thing to do this week", "why": "1 sentence", "effort": "low | medium"}
  ],
  "kpis": [
    {"metric": "metric name", "what_it_measures": "why this matters", "benchmark": "industry benchmark if known, or blank", "evidence_type": "research | inference"}
  ],
  "data_we_cannot_verify": ["list of things we cannot know without GA4 / social API access"],
  "confidence_overall": "high | medium | low",
  "confidence_rationale": "1 sentence"
}
PROMPT,
            'temperature'     => 0.4,
            'max_tokens'      => 2000,
            'response_format' => 'json',
        ]);

        if (!$response['success']) {
            return ['error' => 'Strategy generation failed', 'channels' => [], 'lead_funnel' => []];
        }

        $strategy = $this->parseJson($response['content'], ['channels' => [], 'lead_funnel' => []]);

        // Sort channels by priority
        if (!empty($strategy['channels'])) {
            usort($strategy['channels'], fn($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));
        }

        return $strategy;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parseJson(string $raw, array $fallback): array
    {
        // Strip markdown fences if present
        $clean = preg_replace('/^```json\s*/m', '', $raw);
        $clean = preg_replace('/^```\s*$/m', '', $clean);
        $clean = trim($clean);

        // Find the first { ... } block
        if (!str_starts_with($clean, '{')) {
            if (preg_match('/\{[\s\S]*\}/m', $clean, $m)) {
                $clean = $m[0];
            }
        }

        try {
            return json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            Log::warning('AcquisitionIntelligenceService: JSON parse failed', ['raw' => substr($raw, 0, 200)]);
            return $fallback;
        }
    }
}
