<?php

namespace App\Services\AI;

use App\Models\AiAction;
use App\Models\AiBrief;
use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Models\BusinessGoal;
use App\Models\KnowledgeBase;
use App\Models\PageSnapshot;
use App\Models\RevenueLeak;
use App\Services\AI\AiGatewayService;
use App\Services\Bing\BingWebmasterService;
use App\Services\Scanner\SiteProfileService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BriefGeneratorService
{
    protected AiGatewayService $aiGateway;
    protected SiteProfileService $siteProfileService;
    protected BingWebmasterService $bing;

    public function __construct(
        SiteProfileService $siteProfileService,
        BingWebmasterService $bing,
    ) {
        // Brief generation uses AI_PROVIDER_BRIEF (falls back to AI_PROVIDER).
        // Keeps brief quota separate from real-time scan and chat calls.
        $this->aiGateway = AiGatewayService::forPurpose(AiGatewayService::PURPOSE_BRIEF);
        $this->siteProfileService = $siteProfileService;
        $this->bing = $bing;
    }

    public function generateForBrand(Brand $brand): ?AiBrief
    {
        Log::info('BriefGenerator: Starting for brand', ['brand_id' => $brand->id]);

        if (!$brand->is_active) {
            Log::info('BriefGenerator: Brand is not active', ['brand_id' => $brand->id]);
            return null;
        }

        $recentBrief = AiBrief::where('brand_id', $brand->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->latest('created_at')
            ->first();

        if ($recentBrief) {
            Log::info('BriefGenerator: Brief already generated within the last 24 hours', [
                'brand_id' => $brand->id,
                'brief_id' => $recentBrief->id,
                'created_at' => $recentBrief->created_at?->toDateTimeString(),
            ]);

            return $recentBrief;
        }

        if (!$this->aiGateway->isAvailable()) {
            Log::warning('BriefGenerator: AI service not available', [
                'brand_id' => $brand->id,
                'provider' => $this->aiGateway->getProvider()
            ]);
            return null;
        }

        $hasData = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->exists();

        if (!$hasData) {
            Log::warning('BriefGenerator: No GA4 data found', ['brand_id' => $brand->id]);
            return null;
        }

        // ── 24-hour wall-clock guard ──────────────────────────────────────────
        // Only one brief per brand per calendar day, regardless of data changes.
        // The fingerprint check below additionally skips regeneration when the
        // data hasn't changed within that window (e.g. a manual retry).
        $briefToday = AiBrief::where('brand_id', $brand->id)
            ->whereDate('brief_date', Carbon::today())
            ->orderByDesc('created_at')
            ->first();

        if ($briefToday) {
            Log::info('BriefGenerator: Brief already generated today — skipping', [
                'brand_id' => $brand->id,
                'brief_id' => $briefToday->id,
                'brief_date' => $briefToday->brief_date,
            ]);
            return $briefToday;
        }

        $promptData = $this->buildPromptData($brand);
        $fingerprint = $this->generateFingerprint($promptData);

        // Secondary deduplication: same data fingerprint seen in last 20 hours
        // (catches edge cases like a midnight rollover hitting both days).
        // Deduplication check: avoid regenerating the same dataset more than once per 24-hour window.
        $existingBrief = AiBrief::where('brand_id', $brand->id)
            ->where('fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($existingBrief) {
            Log::info('BriefGenerator: Brief already exists for current dataset', [
                'brand_id'    => $brand->id,
                'fingerprint' => $fingerprint
            ]);
            return $existingBrief;
        }

        Log::info('BriefGenerator: Requesting AI generation', ['brand_id' => $brand->id]);

        $aiResponse = $this->aiGateway->generate([
            'system_prompt'   => $this->getSystemPrompt($brand),
            'user_prompt'     => json_encode($promptData, JSON_PRETTY_PRINT),
            'temperature'     => 0.7,
            'max_tokens'      => 4096,
            'response_format' => 'json',
        ]);

        if (!($aiResponse['success'] ?? false) || empty($aiResponse['content'])) {
            Log::error('BriefGenerator: AI generation failed', [
                'brand_id' => $brand->id,
                'error'    => $aiResponse['error'] ?? 'Empty or unsuccessful response',
            ]);
            return null;
        }

        $parsedData = $this->parseAiResponse($aiResponse['content']);

        if (empty($parsedData)) {
            Log::error('BriefGenerator: Failed to parse valid JSON from AI output', [
                'brand_id' => $brand->id,
                'raw_body' => $aiResponse['content'],
            ]);
            return null;
        }

        return DB::transaction(function () use ($brand, $fingerprint, $parsedData, $aiResponse) {
            // ✅ Idempotent: update if exists, insert if not
            $brief = AiBrief::updateOrCreate(
                [
                    'fingerprint' => $fingerprint,
                ],
                [
                    'brand_id'                 => $brand->id,
                    'brief_date'               => Carbon::today(),
                    'fingerprint'              => $fingerprint,
                    'strategic_diagnosis'      => $parsedData['strategic_diagnosis'] ?? 'No diagnosis provided.',
                    'estimated_revenue_impact' => $parsedData['estimated_revenue_impact'] ?? 0.00,
                    'confidence_score'         => $parsedData['confidence_score'] ?? null,
                    'raw_llm_output'           => $parsedData,
                    'ai_provider'              => $this->aiGateway->getProvider(),
                    'model_used'               => $aiResponse['model_used'] ?? null,
                    'tokens_used'              => $aiResponse['tokens_used'] ?? 0,
                    'response_time_ms'         => $aiResponse['response_time_ms'] ?? 0,
                ]
            );

            if (!empty($parsedData['actions']) && is_array($parsedData['actions'])) {
                foreach ($parsedData['actions'] as $actionData) {
                    // Compute stable key before the duplicate check so it can be used as the primary signal
                    $stableKey = $this->computeStableKey($brand->id, $actionData);

                    if ($this->isDuplicateAction($brand->id, $actionData, $stableKey)) {
                        Log::info('BriefGenerator: Skipped duplicate action', [
                            'brand_id'   => $brand->id,
                            'title'      => $actionData['title'] ?? '(no title)',
                            'category'   => $actionData['category'] ?? 'unknown',
                            'stable_key' => $stableKey,
                        ]);
                        continue;
                    }

                    // ✅ Validate target_url against real page inventory
                    $rawUrl       = $actionData['target_url'] ?? null;
                    $validatedUrl = $this->validateTargetUrl($rawUrl, $brand);

                    if ($rawUrl && !$validatedUrl) {
                        Log::warning('BriefGenerator: Discarded hallucinated target_url', [
                            'brand_id'      => $brand->id,
                            'requested_url' => $rawUrl,
                            'title'         => $actionData['title'] ?? '(no title)',
                        ]);
                    }

                    // Use updateOrCreate keyed on stable_key so concurrent brief runs
                    // or a requeued job cannot insert a duplicate even under a race condition.
                    // If the row already exists (stable_key match) it is refreshed in place
                    // rather than duplicated.
                    AiAction::updateOrCreate(
                        [
                            'brand_id'              => $brand->id,
                            'opportunity_stable_key' => $stableKey,
                        ],
                        [
                            'brief_id'               => $brief->id,
                            'title'                  => $actionData['title'] ?? 'Untitled Action',
                            'category'               => $actionData['category'] ?? 'strategy',
                            'description'            => $actionData['description'] ?? '',
                            'suggested_content'      => $actionData['suggested_content'] ?? null,
                            'content_draft'          => $actionData['content_draft'] ?? null,
                            'target_platform'        => $actionData['target_platform'] ?? null,
                            'target_url'             => $validatedUrl,
                            'estimated_impact'       => $actionData['estimated_impact'] ?? null,
                            'priority'               => $actionData['priority'] ?? 1,
                            'status'                 => 'pending',
                            'origin'                 => 'brief',
                            'opportunity_fingerprint' => md5($stableKey . ($actionData['description'] ?? '')),
                        ]
                    );
                }
            }

            Log::info('BriefGenerator: Successfully generated brief and actions', [
                'brand_id' => $brand->id,
                'brief_id' => $brief->id,
                'actions'  => count($parsedData['actions'] ?? []),
            ]);

            return $brief;
        });
    }

    /**
     * Assemble all analytical context and knowledge data for the prompt payload.
     */
    protected function buildPromptData(Brand $brand): array
    {
        $today      = Carbon::today();
        $last30Days = $today->copy()->subDays(30);
        $last7Days  = $today->copy()->subDays(7);

        $analytics = $this->getAnalyticsSummary($brand, $last30Days, $last7Days);

        $revenueLeaks = RevenueLeak::where('brand_id', $brand->id)
            ->where('status', 'open')
            ->orderBy('estimated_loss', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        $knowledge = KnowledgeBase::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->pluck('content', 'key')
            ->toArray();

        $goals = BusinessGoal::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->get()
            ->toArray();

        $pageSnapshots = $this->getPageSnapshotsForAnalytics($brand, $analytics);

        // Site-wide profile — dominant topics + full page inventory
        $siteProfile = $this->siteProfileService->build($brand->id);

        // Social and audience signals (Meta results, ad audiences, comments, personas, content plan). Optional.
        try {
            $social = app(\App\Services\Analytics\AgentEvidenceService::class)->socialContext($brand->id);
        } catch (\Throwable $e) {
            report($e);
            $social = [];
        }

        // Bing Webmaster Tools — keyword gaps and top real queries. Optional; degrades gracefully.
        $bingKeywordGaps = [];
        $bingTopQueries  = [];
        try {
            $bingKeywordGaps = $this->bing->getKeywordGaps($brand, 15);
            $bingTopQueries  = $this->bing->getRealQueries($brand, 10);
        } catch (\Throwable $e) {
            Log::warning('BriefGenerator: Bing data unavailable', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);
        }

        return [
            'brand' => [
                'name'        => $brand->name,
                'domain_type' => $brand->domain_type ?? 'digital business',
                'brand_voice' => $brand->brand_voice ?? 'Professional, concise, and data-driven',
                'timezone'    => $brand->timezone ?? 'UTC',
                'website_url' => $brand->website_url ?? '',
            ],
            'analytics'           => $analytics,
            'social'              => $social ?: null,
            'revenue_leaks'       => $revenueLeaks,
            'knowledge_base'      => $knowledge,
            'business_goals'      => $goals,
            'page_snapshots'      => $pageSnapshots,
            'bing_keyword_gaps'   => $bingKeywordGaps,
            'bing_top_queries'    => $bingTopQueries,
            'site_inventory' => [
                'dominant_topics' => $siteProfile['dominant_topics'] ?? [],
                'pages'           => array_map(fn($p) => [
                    'url'   => $p['url'],
                    'title' => $p['title'],
                    'type'  => $p['page_type'],
                ], $siteProfile['pages'] ?? []),
            ],
            'date' => $today->toDateString(),
        ];
    }

    /**
     * Get page snapshots for pages mentioned in analytics.
     *
     * Previously fired one LIKE query per top-page URL (N+1).
     * Now fetches all recent snapshots for this brand in a single query,
     * then matches locally — 1 query instead of N.
     */
    protected function getPageSnapshotsForAnalytics(Brand $brand, array $analytics): array
    {
        $snapshots = [];
        $topPages  = $analytics['top_pages'] ?? [];

        if (empty($topPages)) {
            return $snapshots;
        }

        // Fetch the 50 most-recent snapshots once, match locally
        $allSnapshots = PageSnapshot::where('brand_id', $brand->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->keyBy('url');

        foreach ($topPages as $page) {
            $url = $page['dimension'] ?? '';
            if (empty($url)) {
                continue;
            }

            // First try exact match, then substring match within already-loaded collection
            $snapshot = $allSnapshots->get($url)
                ?? $allSnapshots->first(fn($s) => str_contains($s->url, $url) || str_contains($url, $s->url));

            if ($snapshot) {
                $snapshots[] = [
                    'url'              => $snapshot->url,
                    'title'            => $snapshot->title,
                    'page_type'        => $snapshot->page_type,
                    'word_count'       => $snapshot->word_count,
                    'headings'         => $snapshot->headings,
                    'topics_covered'   => $snapshot->topics_covered,
                    'meta_title'       => $snapshot->meta_title,
                    'meta_description' => $snapshot->meta_description,
                    'recommendations'  => $snapshot->recommendations,
                ];
            }
        }

        return $snapshots;
    }

    protected function getAnalyticsSummary(Brand $brand, Carbon $last30Days, Carbon $last7Days): array
    {
        $metrics = [];
        $cumulativeMetrics = ['visitors', 'sessions', 'page_views', 'conversions', 'revenue'];

        foreach ($cumulativeMetrics as $metricName) {
            $data = AnalyticsSnapshot::where('brand_id', $brand->id)
                ->where('source', 'ga4')
                ->where('metric', $metricName)
                ->whereNull('dimension')
                ->where('date', '>=', $last30Days->toDateString())
                ->get();

            $metrics[$metricName] = [
                'total'   => (float) $data->sum('value'),
                'average' => round((float) $data->avg('value'), 2),
                'max'     => round((float) $data->max('value'), 2),
                'min'     => round((float) $data->min('value'), 2),
            ];
        }

        // Base URL comes from the brand model — never falls back to slug.
        $baseUrl = $brand->base_url;

        if (!$baseUrl) {
            Log::warning('BriefGenerator: brand has no website_url, top pages will lack full URLs', [
                'brand_id' => $brand->id,
            ]);
        }

        // Top pages with full URLs when a base URL is available
        $topPages = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('metric', 'visitors')
            ->whereNotNull('dimension')
            ->where('dimension', 'not like', 'source_%')
            ->where('date', '>=', $last30Days->toDateString())
            ->select('dimension')
            ->selectRaw('SUM(value) as total_visitors')
            ->groupBy('dimension')
            ->orderBy('total_visitors', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) use ($baseUrl) {
                $url = $item->dimension;

                if ($baseUrl && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                    $url = $baseUrl . '/' . ltrim($url, '/');
                    $url = preg_replace('/(?<!:)\/+/', '/', $url);
                }

                return [
                    'dimension'      => $url,
                    'total_visitors' => $item->total_visitors,
                ];
            })
            ->toArray();

        $channels = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('metric', 'channel_visitors')
            ->where('date', '>=', $last30Days->toDateString())
            ->select('dimension')
            ->selectRaw('SUM(value) as total_visitors')
            ->groupBy('dimension')
            ->orderBy('total_visitors', 'desc')
            ->get()
            ->toArray();

        return [
            'period' => [
                'last_30_days' => $last30Days->toDateString(),
                'last_7_days'  => $last7Days->toDateString(),
                'today'        => Carbon::today()->toDateString(),
            ],
            'metrics'   => $metrics,
            'top_pages' => $topPages,
            'channels'  => $channels,
        ];
    }

    protected function getSystemPrompt(Brand $brand): string
    {
        $brandVoice = $brand->brand_voice ?? 'Professional and Data-Driven';
        $domainType = $brand->domain_type ?? 'digital business';
        $website    = $brand->website_url ?? '';

        return <<<PROMPT
You are the Chief Marketing Officer for {$brand->name}, a {$domainType} business.

Your objective is to analyze performance metrics, revenue leaks, and business goals to output a high-impact daily brief.

Guiding Principles:
1. Identify the single highest-value opportunity or worst revenue leak.
2. Quantify potential revenue loss or gain directly.
3. Formulate 3-5 distinct, prioritized action items.
4. Voice guidelines: "{$brandVoice}"

USING `bing_keyword_gaps` AND `bing_top_queries` (may be empty arrays):
- `bing_keyword_gaps` = real Bing search queries where the site has impressions but ranks poorly (position > 5) or has very low CTR. These are **confirmed content gaps** — real users searched for these terms and found this site uncompetitive.
- When `bing_keyword_gaps` is non-empty, generate at least 1 "content" or "seo" action targeting the highest-opportunity gap queries. Use the `query` field as the topic/keyword target.
- `bing_top_queries` = real user queries that drive traffic. Use these to understand what users actually want from this site.
- Never invent keyword data. If both arrays are empty, do not mention Bing or search performance.

USING THE `social` BLOCK (Meta results, audience and content plan; may be null):
- Social actions (category "social") must be built from this evidence, and the description must say which signal each one comes from: a real audience question (`audience.comments.recent_questions`), one of our own best posts (`content.performance.top_posts`), an audience segment (`audience.ads.groups`) or a persona pain (`personas`).
- `audience.ads.state` and `content.performance.state`: only "ok" supports a conclusion. "too_early", "no_data" or "no_results" mean there is not enough data yet. They are NOT evidence of poor performance. Never present them as a result or as zero.
- Never name a winning format, platform, topic or weekday unless `content.performance.state` is "ok" and it appears in `leaders`. `top_posts` are individual results, not proof that a format works.
- Do not repeat anything listed in `content.planned_topics`.
- `content.site_impact` is what our posts did on the website (visits and key events such as sign-ups). Judge posts by it, not by likes alone: a post with modest engagement that sends visitors is worth more than a popular one that sends none. It is traffic and sign-up signals, never revenue: do not state or estimate money earned. Only "ok" supports a conclusion; "too_early" and "no_tracked_posts" are not evidence of failure.
- Write the post itself in `suggested_content` in the brand voice, aimed at the persona. Do not promise outcomes.
- Posts are published by a human, or scheduled for automatic publishing with human approval. Do not claim anything was posted.
- If `social` is null, do not invent audience insights: base social ideas only on the analytics and site inventory, and say the Meta data is not available yet.

CRITICAL RULES FOR target_url:
- The user prompt contains a `site_inventory.pages` array listing every page that actually exists on {$website}.
- `target_url` MUST be one of those pages exactly as listed, or null.
- NEVER invent a URL. NEVER guess a slug. NEVER write `/services/web-development` or `/blog/some-slug` unless that exact URL appears in `site_inventory.pages`.
- If no existing page is the right target for an action, set `target_url` to null and describe the target in the description instead.
- Actions without a target_url are still valid — they become "new page" or "strategy" work.

CRITICAL: Return ONLY a valid, raw JSON object matching the exact format below. Do not wrap output in markdown codeblocks (e.g. ```json).

Output Schema:
{
    "strategic_diagnosis": "Clear executive summary describing performance, opportunities, or leaks.",
    "estimated_revenue_impact": 1500.00,
    "confidence_score": 85,
    "actions": [
        {
            "title": "Actionable task title",
            "category": "seo|content|social|email|web_copy|campaign|strategy|analytics",
            "description": "Step-by-step guidance on execution.",
            "suggested_content": "Short text for emails or social posts.",
            "content_draft": "Complete copy draft for blogs, web pages, or newsletters.",
            "target_platform": "facebook|instagram|tiktok|linkedin|twitter|blog|email",
            "target_url": "/page-path-from-site_inventory-or-null",
            "estimated_impact": 500.00,
            "priority": 1
        }
    ]
}
PROMPT;
    }

    protected function parseAiResponse(string $response): array
    {
        try {
            $cleaned = trim($response);
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);

            $data = json_decode($cleaned, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $firstBrace = strpos($cleaned, '{');
                $lastBrace  = strrpos($cleaned, '}');

                if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                    $candidate = substr($cleaned, $firstBrace, ($lastBrace - $firstBrace) + 1);
                    $data = json_decode($candidate, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                }

                if (json_last_error() !== JSON_ERROR_NONE && $firstBrace !== false) {
                    $candidate = substr($cleaned, $firstBrace);
                    $lastComma = strrpos($candidate, ',');

                    if ($lastComma !== false && $lastComma > 0) {
                        $candidate = substr($candidate, 0, $lastComma) . '}';
                        $data = json_decode($candidate, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                    }
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::error('BriefGenerator: JSON decode failed', [
                    'error' => json_last_error_msg(),
                    'raw'   => $response,
                ]);
                return [];
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('BriefGenerator: Exception while parsing response', ['error' => $e->getMessage()]);
            return [];
        }
    }

    protected function generateFingerprint(array $promptData): string
    {
        unset($promptData['date']);
        if (isset($promptData['analytics']['period'])) {
            unset($promptData['analytics']['period']);
        }

        $this->recursiveKsort($promptData);
        return hash('sha256', json_encode($promptData));
    }

    private function recursiveKsort(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
    }

    /**
     * Check whether a very similar action already exists for this brand.
     * Prevents the brief generator from filling the queue with
     * near-duplicate recommendations day after day.
     */
    /**
     * Compute a stable deduplication key for an action.
     *
     * The key is a short hash of brand + category + normalised title tokens.
     * It is stable across brief runs: the same conceptual action suggested on
     * Monday and again on Friday produces the same key, so the second one is
     * silently skipped rather than inserted as a duplicate.
     *
     * The key intentionally excludes description and content — those can evolve
     * without creating a "new" action from the user's perspective.
     */
    protected function computeStableKey(int $brandId, array $actionData): string
    {
        $category = $actionData['category'] ?? 'strategy';
        $sig      = $this->titleSignature($actionData['title'] ?? '');

        return substr(hash('sha256', "{$brandId}|{$category}|{$sig}"), 0, 32);
    }

    /**
     * Check whether an action with the same meaning already exists.
     *
     * Three signals, in order of specificity:
     *   1. Stable key match — fastest, DB index hit, catches exact same concept.
     *   2. Same target URL + same category in last 30 days — catches URL-level
     *      duplicates even when the title was rephrased by the AI.
     *   3. Title signature match across all categories in last 30 days — catches
     *      the same action reclassified (e.g. 'content' one day, 'seo' the next).
     *
     * Window is 30 days (was 7) so weekly recurring briefs don't re-queue the
     * same action every week. Rejected actions are excluded — a rejected action
     * can legitimately resurface after the window.
     */
    protected function isDuplicateAction(int $brandId, array $actionData, string $stableKey): bool
    {
        // 1. Stable key — primary deduplication signal (uses DB index)
        $stableKeyExists = AiAction::where('brand_id', $brandId)
            ->where('opportunity_stable_key', $stableKey)
            ->whereNotIn('status', ['rejected'])
            ->exists();

        if ($stableKeyExists) {
            return true;
        }

        $cutoff    = now()->subDays(30);
        $category  = $actionData['category'] ?? 'strategy';
        $targetUrl = $actionData['target_url'] ?? null;
        $newSig    = $this->titleSignature($actionData['title'] ?? '');

        // 2. Same URL + same category (page-specific actions shouldn't repeat)
        if ($targetUrl) {
            $urlDuplicate = AiAction::where('brand_id', $brandId)
                ->where('target_url', $targetUrl)
                ->where('category', $category)
                ->where('created_at', '>=', $cutoff)
                ->whereNotIn('status', ['rejected'])
                ->exists();

            if ($urlDuplicate) {
                return true;
            }
        }

        // 3. Title signature — load recent candidates and compare normalised tokens.
        // Only load title + status; avoids pulling large text columns.
        $candidates = AiAction::where('brand_id', $brandId)
            ->where('created_at', '>=', $cutoff)
            ->whereNotIn('status', ['rejected'])
            ->get(['title']);

        foreach ($candidates as $existing) {
            if ($this->titleSignature($existing->title) === $newSig) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalise a title into a sorted bag-of-words signature.
     *
     * Strips casing, punctuation, action verbs and stop words, then sorts the
     * remaining tokens. "Optimise blog post on Buganda" and "Blog content
     * improvement: Buganda Kingdom" both produce the same signature.
     */
    protected function titleSignature(string $title): string
    {
        static $stop = [
            // articles / prepositions
            'the', 'a', 'an', 'and', 'or', 'for', 'to', 'of', 'in', 'on',
            'with', 'at', 'by', 'from', 'into', 'about', 'as', 'is', 'are',
            // possessives / pronouns
            'our', 'your', 'their', 'its',
            // common action verbs (high noise, low meaning)
            'launch', 'optimize', 'optimise', 'create', 'publish', 'implement',
            'revise', 'improve', 'update', 'write', 'add', 'build', 'run',
            'develop', 'increase', 'boost', 'enhance', 'grow', 'drive', 'use',
            // marketing filler
            'content', 'page', 'post', 'campaign', 'strategy', 'new', 'better',
        ];

        $clean  = strtolower($title);
        $clean  = preg_replace('/[^a-z0-9 ]+/', ' ', $clean);
        $tokens = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter($tokens, fn($t) => !in_array($t, $stop, true)));
        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * Validate a target_url against the brand's real page inventory.
     *
     * Returns the canonical full URL if it matches a real page,
     * or null if the URL doesn't exist / can't be resolved.
     *
     * The LLM frequently invents plausible-looking slugs. This catches them.
     */
    protected function validateTargetUrl(?string $url, Brand $brand): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = trim($url);

        $realUrls = PageSnapshot::where('brand_id', $brand->id)
            ->where('status', 'completed')
            ->pluck('url')
            ->toArray();

        if (empty($realUrls)) {
            // No scans yet — we can't validate anything. Reject to be safe.
            return null;
        }

        $normalize = function (string $candidate): string {
            // Full URL → strip to path
            if (preg_match('#^https?://#i', $candidate)) {
                $parsed    = parse_url($candidate);
                $candidate = $parsed['path'] ?? '/';
            }

            // Drop query string and fragment
            $candidate = strtok($candidate, '?#');

            // Strip trailing slash
            $candidate = rtrim($candidate, '/');

            // Ensure leading slash
            if ($candidate === '' || $candidate[0] !== '/') {
                $candidate = '/' . $candidate;
            }

            return strtolower($candidate);
        };

        $needle = $normalize($url);

        foreach ($realUrls as $real) {
            if ($normalize($real) === $needle) {
                // Return the canonical full URL from the snapshot
                return $real;
            }
        }

        return null;
    }
}
