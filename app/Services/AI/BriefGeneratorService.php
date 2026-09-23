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
use App\Services\Scanner\SiteProfileService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BriefGeneratorService
{
    protected AiGatewayService $aiGateway;
    protected SiteProfileService $siteProfileService;

    public function __construct(
        AiGatewayService $aiGateway,
        SiteProfileService $siteProfileService
    ) {
        $this->aiGateway = $aiGateway;
        $this->siteProfileService = $siteProfileService;
    }

    public function generateForBrand(Brand $brand): ?AiBrief
    {
        Log::info('BriefGenerator: Starting for brand', ['brand_id' => $brand->id]);

        if (!$brand->is_active) {
            Log::info('BriefGenerator: Brand is not active', ['brand_id' => $brand->id]);
            return null;
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

        $promptData = $this->buildPromptData($brand);
        $fingerprint = $this->generateFingerprint($promptData);

        // Deduplication check: return existing brief if data hasn't changed today
        $existingBrief = AiBrief::where('brand_id', $brand->id)
            ->where('fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subHours(20))
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
                    if ($this->isDuplicateAction($brand->id, $actionData)) {
                        Log::info('BriefGenerator: Skipped duplicate action', [
                            'brand_id' => $brand->id,
                            'title'    => $actionData['title'] ?? '(no title)',
                            'category' => $actionData['category'] ?? 'unknown',
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

                    AiAction::create([
                        'brand_id'          => $brand->id,
                        'brief_id'          => $brief->id,
                        'title'             => $actionData['title'] ?? 'Untitled Action',
                        'category'          => $actionData['category'] ?? 'strategy',
                        'description'       => $actionData['description'] ?? '',
                        'suggested_content' => $actionData['suggested_content'] ?? null,
                        'content_draft'     => $actionData['content_draft'] ?? null,
                        'target_platform'   => $actionData['target_platform'] ?? null,
                        'target_url'        => $validatedUrl,
                        'estimated_impact'  => $actionData['estimated_impact'] ?? null,
                        'priority'          => $actionData['priority'] ?? 1,
                        'status'            => 'pending',
                        'origin'            => 'brief',
                    ]);
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

        return [
            'brand' => [
                'name'        => $brand->name,
                'domain_type' => $brand->domain_type ?? 'digital business',
                'brand_voice' => $brand->brand_voice ?? 'Professional, concise, and data-driven',
                'timezone'    => $brand->timezone ?? 'UTC',
                'website_url' => $brand->website_url ?? '',
            ],
            'analytics'      => $analytics,
            'revenue_leaks'  => $revenueLeaks,
            'knowledge_base' => $knowledge,
            'business_goals' => $goals,
            'page_snapshots' => $pageSnapshots,
            'site_inventory' => [
                'dominant_topics' => $siteProfile['dominant_topics'] ?? [],
                'pages'           => array_map(fn ($p) => [
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
     */
    protected function getPageSnapshotsForAnalytics(Brand $brand, array $analytics): array
    {
        $snapshots = [];

        $topPages = $analytics['top_pages'] ?? [];

        foreach ($topPages as $page) {
            $url = $page['dimension'] ?? '';
            if (empty($url)) {
                continue;
            }

            $snapshot = PageSnapshot::where('brand_id', $brand->id)
                ->where('url', 'like', '%' . $url . '%')
                ->orderBy('created_at', 'desc')
                ->first();

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
            ->where('metric', 'visitors')
            ->where('dimension', 'like', 'source_%')
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
            "target_platform": "facebook|linkedin|twitter|blog|email",
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
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($response));
            $data = json_decode($cleaned, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('BriefGenerator: JSON decode failed', [
                    'error' => json_last_error_msg(),
                    'raw'   => $response
                ]);
                return [];
            }

            return is_array($data) ? $data : [];
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
    protected function isDuplicateAction(int $brandId, array $actionData): bool
    {
        $category  = $actionData['category'] ?? 'strategy';
        $title     = $actionData['title'] ?? '';
        $targetUrl = $actionData['target_url'] ?? null;

        $cutoff = now()->subDays(7);

        $candidates = AiAction::where('brand_id', $brandId)
            ->where('category', $category)
            ->where('created_at', '>=', $cutoff)
            ->whereIn('status', ['pending', 'approved', 'content_generated'])
            ->get(['id', 'title', 'target_url']);

        $newSignature = $this->titleSignature($title);

        foreach ($candidates as $existing) {
            if ($targetUrl && $existing->target_url === $targetUrl) {
                return true;
            }

            $existingSig = $this->titleSignature($existing->title);
            if ($newSignature === $existingSig) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a title into a comparable signature.
     * Strips casing, punctuation, common filler words, and sorts tokens.
     */
    protected function titleSignature(string $title): string
    {
        $stop = [
            'the', 'a', 'an', 'and', 'or', 'for', 'to', 'of', 'in', 'on',
            'with', 'our', 'your', 'launch', 'optimize', 'create', 'publish',
            'implement', 'revise', 'improve',
        ];

        $clean  = strtolower($title);
        $clean  = preg_replace('/[^a-z0-9 ]+/', ' ', $clean);
        $tokens = preg_split('/\s+/', trim($clean));
        $tokens = array_filter($tokens, fn ($t) => $t !== '' && !in_array($t, $stop, true));
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