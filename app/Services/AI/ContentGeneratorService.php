<?php

namespace App\Services\AI;

use App\Models\AiAction;
use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\GuardianAuditLog;
use App\Models\KnowledgeBase;
use App\Models\PageSnapshot;
use App\Models\User;
use App\Services\AI\AiGatewayService;
use App\Services\AI\ContentServicePolicy;
use App\Services\ContentTourMatcher;
use App\Services\Scanner\PageScannerService;
use App\Services\Scanner\SiteProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContentGeneratorService
{
    protected PageScannerService $scanner;
    protected AiGatewayService $aiGateway;
    protected ContentServicePolicy $contentPolicy;
    protected SiteProfileService $siteProfileService;

    public function __construct(
        AiGatewayService $aiGateway,
        PageScannerService $scanner,
        ContentServicePolicy $contentPolicy,
        SiteProfileService $siteProfileService
    ) {
        $this->aiGateway = $aiGateway;
        $this->scanner = $scanner;
        $this->contentPolicy = $contentPolicy;
        $this->siteProfileService = $siteProfileService;
    }

    /**
     * Generate content for an approved action.
     */
    public function generateForAction(AiAction $action, ?User $user = null): ?ContentDraft
    {
        // 🔒 Authorization
        if ($user !== null) {
            if (!Gate::forUser($user)->allows('create', ContentDraft::class)) {
                Log::warning('Unauthorized content generation attempt', [
                    'user_id'   => $user->id,
                    'action_id' => $action->id,
                    'brand_id'  => $action->brand_id,
                ]);
                throw new AuthorizationException('You are not authorized to generate content.');
            }

            if (!$user->belongsToBrand($action->brand_id) && !$user->hasRole('super-admin')) {
                throw new AuthorizationException('You do not have access to this brand.');
            }
        }

        if ($action->status !== 'approved') {
            Log::warning('ContentGenerator: Action is not approved', [
                'action_id' => $action->id,
                'status'    => $action->status,
            ]);
            return null;
        }

        $brand = $action->brand;
        if (!$brand) {
            Log::error('ContentGenerator: Associated brand not found', ['action_id' => $action->id]);
            return null;
        }

        // 🔒 Content policy: rate limits + blocked topics + output validation
        $policyCheck = $this->contentPolicy->canGenerate(
            $brand,
            $action->title ?? '',
            $action->category ?? 'blog'
        );
        if (!$policyCheck['allowed']) {
            Log::warning('Content generation denied by service policy', [
                'action_id' => $action->id,
                'reason'    => $policyCheck['reason'],
            ]);
            return null;
        }

        if (ContentDraft::where('action_id', $action->id)->exists()) {
            return ContentDraft::where('action_id', $action->id)->first();
        }

        // ── Site profile: dominant topics + page inventory ──
        $siteProfile = $this->siteProfileService->build($brand->id);

        // ── KnowledgeBase: business description, audience, pillars, forbidden topics ──
        $knowledge = KnowledgeBase::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->pluck('content', 'key')
            ->toArray();

        // ── On-brand pre-check ──
        $topic = $action->target_keyword ?: $action->title ?: '';
        $onBrand = $this->isOnBrand($topic, $knowledge, $siteProfile);

        if (!$onBrand['ok']) {
            Log::warning('ContentGenerator: Off-brand topic rejected before generation', [
                'action_id' => $action->id,
                'topic'     => $topic,
                'reason'    => $onBrand['reason'],
            ]);
            return null;
        }

        // ── Page snapshot (if a target URL exists) ──
        $pageSnapshot = null;
        if ($action->target_url) {
            $pageSnapshot = PageSnapshot::where('brand_id', $brand->id)
                ->where('url', 'like', '%' . $action->target_url . '%')
                ->orderBy('created_at', 'desc')
                ->first();

            Log::info('ContentGenerator: Page snapshot lookup', [
                'action_id'   => $action->id,
                'found'       => $pageSnapshot !== null,
                'snapshot_id' => $pageSnapshot?->id,
            ]);
        }

        // ── Build prompts (now with site profile + knowledge) ──
        $promptData = $this->buildPrompt($action, $brand, $pageSnapshot, $siteProfile, $knowledge);

        $response = $this->aiGateway->generate([
            'system_prompt'   => $this->getSystemPrompt($brand, $action->category, $pageSnapshot, $siteProfile, $knowledge),
            'user_prompt'     => json_encode($promptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'temperature'     => 0.7,
            'max_tokens'      => 4096,
            'response_format' => 'json',
        ]);

        if (!($response['success'] ?? false) || empty($response['content'])) {
            Log::error('ContentGenerator: AI execution failed', [
                'action_id' => $action->id,
                'error'     => $response['error'] ?? 'Unknown or empty response',
            ]);
            return null;
        }

        $contentData = $this->parseContentResponse($response['content'], $action->category);

        $validation = $this->contentPolicy->validateOutput(
            $contentData['content'] ?? '',
            $action->category
        );
        if (!$validation['valid']) {
            Log::error('Content validation failed', [
                'action_id' => $action->id,
                'reason'    => $validation['reason'],
            ]);
            return null;
        }

        // ── Persist atomically ──
        try {
            return DB::transaction(function () use (
                $action, $brand, $contentData, $promptData, $response, $pageSnapshot, $siteProfile
            ) {
                $draft = ContentDraft::create([
                    'brand_id'         => $brand->id,
                    'action_id'        => $action->id,
                    'title'            => $contentData['title'] ?? $action->title,
                    'type'             => $this->getContentType($action->category),
                    'content'          => $contentData['content'] ?? '',
                    'excerpt'          => $contentData['excerpt'] ?? null,
                    'target_keyword'   => $contentData['target_keyword'] ?? null,
                    'meta_title'       => $contentData['meta_title'] ?? null,
                    'meta_description' => $contentData['meta_description'] ?? null,
                    'seo_data'         => $contentData['seo_data'] ?? null,
                    'status'           => 'draft',
                    'metadata'         => [
                        'provider'          => $this->aiGateway->getProvider(),
                        'model'             => $response['model_used'] ?? null,
                        'tokens_used'       => $response['tokens_used'] ?? 0,
                        'page_snapshot_id'  => $pageSnapshot?->id,
                        'site_profile_pages'=> $siteProfile['page_count'] ?? 0,
                    ],
                ]);

                $action->update(['status' => 'content_generated']);

                $this->logToGuardian($brand->id, $action->id, $promptData, $response);

                Log::info('ContentGenerator: Draft created successfully', [
                    'action_id' => $action->id,
                    'draft_id'  => $draft->id,
                    'type'      => $draft->type,
                ]);

                // Tour matching (enforced CTA)
                try {
                    $matcher = app(ContentTourMatcher::class);
                    $matches = $matcher->findMatches($draft);

                    if ($matches->isNotEmpty()) {
                        $matcher->applyMatch($draft, $matches);
                        Log::info('Tour match enforced', [
                            'draft_id' => $draft->id,
                            'tour_id'  => $draft->tour_package_id,
                            'score'    => $draft->tour_match_score,
                            'enforced' => $draft->tour_enforced,
                        ]);
                    } else {
                        Log::info('No tour match found for draft', ['draft_id' => $draft->id]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Tour matching failed', [
                        'draft_id' => $draft->id,
                        'error'    => $e->getMessage(),
                    ]);
                }

                return $draft;
            });
        } catch (\Throwable $e) {
            Log::error('ContentGenerator: Failed to save generated draft', [
                'action_id' => $action->id,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate content for a brand directly (agent API or human trigger).
     */
    public function generateContent(
        int $brandId,
        string $topic,
        string $template = 'blog',
        ?User $user = null
    ): ContentDraft {
        $brand = Brand::findOrFail($brandId);

        $categoryMap = [
            'blog'     => 'content',
            'social'   => 'social',
            'email'    => 'email',
            'web_copy' => 'web_copy',
        ];
        $category = $categoryMap[$template] ?? 'content';

        $action = AiAction::create([
            'brand_id'        => $brandId,
            'title'           => 'Generate content: ' . substr($topic, 0, 100),
            'description'     => "Generated by agent for topic: {$topic}",
            'category'        => $category,
            'target_platform' => $template,
            'target_keyword'  => $topic,
            'status'          => 'approved',
            'origin'          => 'agent',
        ]);

        $draft = $this->generateForAction($action, $user);

        if (!$draft) {
            throw new \Exception('Content generation failed. See logs for the specific reason (may be off-brand, rate-limited, or AI failure).');
        }

        return $draft;
    }

    // ============================================================
    // On-brand guard
    // ============================================================

    /**
     * Refuse topics that clearly don't belong on this brand's site.
     * Deterministic — no LLM call. Reads forbidden_topics from
     * KnowledgeBase and cross-checks against the site profile's
     * dominant topics.
     */
    protected function isOnBrand(string $topic, array $knowledge, array $siteProfile): array
    {
        $topic = strtolower(trim($topic));

        if ($topic === '') {
            return ['ok' => true, 'reason' => null];
        }

        // 1. Forbidden topics from KnowledgeBase
        $forbidden = strtolower($knowledge['forbidden_topics'] ?? '');
        if ($forbidden !== '') {
            // Split on common separators
            $tokens = preg_split('/[,;]+/', $forbidden);
            foreach ($tokens as $token) {
                $token = trim($token);
                $token = preg_replace('/^never write about:?\s*/i', '', $token);
                $token = preg_replace('/^(and|or)\s+/i', '', $token);
                if (strlen($token) < 4) {
                    continue;
                }
                if (str_contains($topic, $token)) {
                    return [
                        'ok'     => false,
                        'reason' => "Topic matches forbidden keyword: '{$token}'",
                    ];
                }
            }
        }

        // 2. Hard-coded off-brand patterns (a safety net independent of KB)
        $hardBlock = [
            'google business profile',
            'local seo for shops',
            'small business marketing',
            'web development tutorial',
            'saas',
            'tech startup',
            'how to start a business',
            'digital marketing agency',
        ];
        foreach ($hardBlock as $pattern) {
            if (str_contains($topic, $pattern)) {
                return [
                    'ok'     => false,
                    'reason' => "Topic matches off-brand pattern: '{$pattern}'",
                ];
            }
        }

        // Everything else passes — we'd rather generate a slightly-off
        // draft that a human reviews than silently refuse on-brand content
        // that just happens to use unusual words.
        return ['ok' => true, 'reason' => null];
    }

    // ============================================================
    // Prompt builders
    // ============================================================

    protected function buildPrompt(
        AiAction $action,
        Brand $brand,
        ?PageSnapshot $snapshot = null,
        array $siteProfile = [],
        array $knowledge = []
    ): array {
        $prompt = [
            'brand' => [
                'name'              => $brand->name,
                'voice'             => $knowledge['brand_voice'] ?? $brand->brand_voice ?? 'Professional and engaging',
                'domain'            => $brand->domain_type ?? 'digital business',
                'website'           => $brand->website_url ?? '',
                'business_description' => $knowledge['business_description'] ?? '',
                'target_audience'   => $knowledge['target_audience'] ?? '',
                'content_pillars'   => $knowledge['content_pillars'] ?? '',
            ],
            'action' => [
                'title'           => $action->title,
                'description'     => $action->description,
                'category'        => $action->category,
                'target_platform' => $action->target_platform,
                'target_url'      => $action->target_url,
            ],
            'knowledge_base' => $knowledge,
            'site_inventory' => $this->formatSiteProfileForPrompt($siteProfile),
            'requirements' => [
                'length' => $this->getLengthRequirement($action->category),
                'tone'   => 'professional yet approachable',
                'format' => $this->getFormatRequirement($action->category),
            ],
        ];

        if ($snapshot) {
            $prompt['page_snapshot'] = [
                'url'              => $snapshot->url,
                'title'            => $snapshot->title,
                'page_type'        => $snapshot->page_type,
                'word_count'       => $snapshot->word_count,
                'headings'         => $snapshot->headings,
                'topics_covered'   => $snapshot->topics_covered,
                'meta_title'       => $snapshot->meta_title,
                'meta_description' => $snapshot->meta_description,
                'recommendations'  => $snapshot->recommendations,
                'has_content'      => !empty($snapshot->content),
                'content_preview'  => substr(strip_tags($snapshot->content ?? ''), 0, 500),
            ];
        }

        return $prompt;
    }

    protected function formatSiteProfileForPrompt(array $siteProfile): array
    {
        if (empty($siteProfile['pages'])) {
            return [
                'note'            => 'No page snapshots available. Do not invent internal URLs — use [INTERNAL: page-type] placeholders only.',
                'dominant_topics' => [],
                'pages'           => [],
            ];
        }

        return [
            'dominant_topics' => $siteProfile['dominant_topics'] ?? [],
            'page_count'      => $siteProfile['page_count'] ?? 0,
            'pages'           => array_map(function ($p) {
                return [
                    'url'       => $p['url'],
                    'type'      => $p['page_type'],
                    'title'     => $p['title'],
                    'h1'        => $p['h1'] ?? null,
                    'topics'    => array_slice($p['topics_covered'] ?? [], 0, 5),
                ];
            }, $siteProfile['pages']),
        ];
    }

    protected function getSystemPrompt(
        Brand $brand,
        string $category,
        ?PageSnapshot $snapshot = null,
        array $siteProfile = [],
        array $knowledge = []
    ): string {
        // KnowledgeBase is the source of truth for brand_voice (that's what the
        // seeder populates); the Brand column is a legacy fallback for brands
        // that predate the KnowledgeBase table.
        $tone = $knowledge['brand_voice'] ?? $brand->brand_voice ?? 'Professional, clear, and compelling';
        $name = $brand->name;

        // Brand context block (used by all content types)
        $business      = $knowledge['business_description'] ?? '';
        $audience      = $knowledge['target_audience'] ?? '';
        $pillars       = $knowledge['content_pillars'] ?? '';
        $forbidden     = $knowledge['forbidden_topics'] ?? '';
        $dominantList  = implode(', ', $siteProfile['dominant_topics'] ?? []);
        $siteSitemap   = $this->buildSitemapBlock($siteProfile);

        $brandBlock = <<<BLOCK
You write for {$name}.

BUSINESS CONTEXT:
{$business}

TARGET AUDIENCE:
{$audience}

CONTENT PILLARS (every piece must fit one of these):
{$pillars}

FORBIDDEN TOPICS (never write about these):
{$forbidden}

DOMINANT SITE TOPICS (based on scans of the live site):
{$dominantList}

SITE INVENTORY — pages that actually exist on {$brand->website_url}:
{$siteSitemap}

RULES FOR INTERNAL LINKS:
- Only link to pages listed in the SITE INVENTORY above.
- If you need to reference a page that isn't listed, use the placeholder [INTERNAL: about] or [INTERNAL: services] — never invent a raw URL.
- Never fabricate URLs like /services/web-development or /blog/some-slug.
BLOCK;

        // SEO meta description path
        if ($category === 'seo') {
            $pageContext = '';
            if ($snapshot) {
                $pageContext = sprintf("The page being optimized is: %s\n", $snapshot->title ?? $snapshot->url ?? 'Unknown page');
                if (!empty($snapshot->headings['h1'])) {
                    $pageContext .= sprintf("Page H1: %s\n", $snapshot->headings['h1']);
                }
                if (!empty($snapshot->topics_covered)) {
                    $pageContext .= sprintf("Topics on this page: %s\n", implode(', ', array_slice($snapshot->topics_covered, 0, 10)));
                }
                if (!empty($snapshot->meta_title)) {
                    $pageContext .= sprintf("Current meta title: %s\n", $snapshot->meta_title);
                }
                if (!empty($snapshot->meta_description)) {
                    $pageContext .= sprintf("Current meta description: %s\n", $snapshot->meta_description);
                }
            }

            return <<<PROMPT
{$brandBlock}

You are an SEO expert for {$name}.

{$pageContext}

CRITICAL: This is a META DESCRIPTION action. Generate ONLY a meta description based on the actual page content.

REQUIREMENTS:
- EXACTLY 140-160 characters total
- Include the target keyword naturally
- Be compelling and click-worthy
- Match the actual content of the page (use the page context above)
- DO NOT write a blog post
- DO NOT write headings or paragraphs

Output ONLY valid JSON:
{
    "title": "Page title (50-60 chars)",
    "meta_title": "SEO meta title (50-60 chars)",
    "meta_description": "Your 140-160 character meta description",
    "target_keyword": "The target keyword"
}
PROMPT;
        }

        // Body content paths
        $pageContext = '';
        if ($snapshot) {
            $pageContext = sprintf(
                "\n\nPAGE CONTEXT:\n- Title: %s\n- Page Type: %s\n- Existing Headings: %s\n- Topics Covered: %s\n- Word Count: %s\n- Recommendations: %s\n",
                $snapshot->title ?? 'Unknown',
                $snapshot->page_type ?? 'Unknown',
                json_encode($snapshot->headings ?? []),
                implode(', ', array_slice($snapshot->topics_covered ?? [], 0, 10)),
                $snapshot->word_count ?? 0,
                implode(', ', array_slice($snapshot->recommendations ?? [], 0, 3))
            );
        }

        $categoryInstructions = match ($category) {
            'content' => sprintf(
                "You are generating a full blog post.\n\nREQUIREMENTS:\n- 1200-2000 words\n- Clear structure with H2, H3 headings\n- Strong introduction and conclusion\n- Use bullet points and lists where appropriate\n- Only reference pages listed in SITE INVENTORY\n%s",
                $pageContext
            ),
            'social' => sprintf(
                "You are generating a social media post.\n\nREQUIREMENTS:\n- 100-200 words\n- Engaging and conversational\n- Relevant hashtags\n- Strong call-to-action\n%s",
                $pageContext
            ),
            'email' => sprintf(
                "You are generating an email.\n\nREQUIREMENTS:\n- 300-500 words\n- Compelling subject line (40-60 chars)\n- Clear body structure\n- Strong call-to-action\n%s",
                $pageContext
            ),
            'web_copy' => sprintf(
                "You are generating web copy (landing page or section).\n\nREQUIREMENTS:\n- 500-800 words\n- Clear headlines and subheadings\n- Persuasive and conversion-focused\n- Strong call-to-action\n%s",
                $pageContext
            ),
            default => "You are generating general content.\n\nREQUIREMENTS:\n- 500-800 words\n- Clear structure\n- Engaging and informative",
        };

        return <<<PROMPT
{$brandBlock}

Writing Style: "{$tone}"

{$categoryInstructions}

BEFORE YOU WRITE, verify the topic fits one of the content pillars listed above.
If it does not fit any pillar, respond with:
{"error": "off_brand", "reason": "brief explanation"}

Otherwise, produce ONLY valid JSON matching the schema below. No preamble, no explanation, no markdown wrappers.

Output Schema:
{
    "title": "The article title",
    "content": "The full content with proper markdown formatting",
    "excerpt": "A short 150-200 word summary",
    "target_keyword": "Primary target keyword",
    "meta_title": "SEO meta title (50-60 characters)",
    "meta_description": "SEO meta description (140-160 characters)",
    "seo_data": {
        "readability_score": 75,
        "keyword_density": 2.0,
        "word_count": 1200,
        "suggested_tags": ["tag1", "tag2"]
    }
}
PROMPT;
    }

    protected function buildSitemapBlock(array $siteProfile): string
    {
        if (empty($siteProfile['pages'])) {
            return '(no page snapshots available — do not invent URLs)';
        }

        $lines = [];
        foreach ($siteProfile['pages'] as $page) {
            $title = $page['title'] ?? 'Untitled';
            $type  = $page['page_type'] ?? 'other';
            $lines[] = sprintf('- %s — "%s" (%s)', $page['url'], $title, $type);
        }

        return implode("\n", $lines);
    }

    // ============================================================
    // Existing helpers (unchanged)
    // ============================================================

    protected function getContentType(string $category): string
    {
        return match ($category) {
            'seo'               => 'seo_meta',
            'content'           => 'blog',
            'social'            => 'social',
            'email', 'campaign' => 'email',
            'web_copy'          => 'web_copy',
            default             => 'blog',
        };
    }

    protected function getLengthRequirement(string $category): string
    {
        return match ($category) {
            'seo'      => '140-160 characters (meta description)',
            'content'  => '1200-2000 words',
            'social'   => '100-200 words',
            'email'    => '300-500 words',
            'web_copy' => '500-800 words',
            'campaign' => '400-600 words',
            default    => '500-800 words',
        };
    }

    protected function getFormatRequirement(string $category): string
    {
        return match ($category) {
            'seo'      => 'Meta description only. 140-160 characters.',
            'content'  => 'Blog post with markdown headings (H2, H3), bullet points, strong conclusion.',
            'social'   => 'Social post with hashtags and emojis.',
            'email'    => 'Subject line, preview text, body, call-to-action.',
            'web_copy' => 'Landing page copy with headlines, benefits, conversion CTAs.',
            'campaign' => 'Promotional email with offer hooks and CTAs.',
            default    => 'Well-structured markdown content.',
        };
    }

    protected function parseContentResponse(string $response, string $category): array
    {
        try {
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($response));
            $data = json_decode($cleaned, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $firstBrace = strpos($cleaned, '{');
                $lastBrace  = strrpos($cleaned, '}');
                if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                    $jsonCandidate = substr($cleaned, $firstBrace, ($lastBrace - $firstBrace) + 1);
                    $data = json_decode($jsonCandidate, true);
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::warning('ContentGenerator: JSON decode failed, using raw response');
                return [
                    'title'            => 'Generated Draft',
                    'content'          => $response,
                    'excerpt'          => Str::limit(strip_tags($response), 200),
                    'target_keyword'   => null,
                    'meta_title'       => null,
                    'meta_description' => null,
                    'seo_data'         => null,
                ];
            }

            // LLM refused as off-brand
            if (($data['error'] ?? null) === 'off_brand') {
                Log::warning('ContentGenerator: LLM refused topic as off-brand', [
                    'reason' => $data['reason'] ?? 'no reason given',
                ]);
                throw new \RuntimeException('LLM refused topic: ' . ($data['reason'] ?? 'off-brand'));
            }

            if ($category === 'seo') {
                $metaDesc  = $data['meta_description'] ?? '';
                $metaTitle = $data['meta_title'] ?? '';
                if (mb_strlen($metaDesc) > 160) {
                    $data['meta_description'] = mb_substr($metaDesc, 0, 157) . '...';
                }
                if (mb_strlen($metaTitle) > 60) {
                    $data['meta_title'] = mb_substr($metaTitle, 0, 57) . '...';
                }
                $data['content'] = $data['meta_description'] ?? $data['meta_title'] ?? 'SEO meta content';
            }

            if (empty($data['content']) && $category !== 'seo') {
                $data['content'] = $data['body'] ?? $data['text'] ?? $data['article'] ?? '';
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('ContentGenerator: Error parsing AI response', [
                'error' => $e->getMessage(),
            ]);
            return [
                'title'            => 'Generated Draft',
                'content'          => $response,
                'excerpt'          => Str::limit(strip_tags($response), 200),
                'target_keyword'   => null,
                'meta_title'       => null,
                'meta_description' => null,
                'seo_data'         => null,
            ];
        }
    }

    protected function logToGuardian(int $brandId, int $actionId, array $promptData, array $response): void
    {
        GuardianAuditLog::create([
            'brand_id'         => $brandId,
            'user_id'          => null,
            'fingerprint'      => 'content_' . $actionId . '_' . time(),
            'event_type'       => 'content_generated',
            'prompt_sent'      => json_encode($promptData, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            'raw_response'     => $response['content'] ?? null,
            'ai_provider'      => $this->aiGateway->getProvider(),
            'model_used'       => $response['model_used'] ?? null,
            'tokens_used'      => $response['tokens_used'] ?? 0,
            'response_time_ms' => $response['response_time_ms'] ?? 0,
            'metadata'         => ['action_id' => $actionId],
        ]);
    }

    public function analyzeGap(int $brandId, string $topic): array
    {
        $existing = ContentDraft::where('brand_id', $brandId)
            ->where('title', 'LIKE', "%{$topic}%")
            ->orWhere('content', 'LIKE', "%{$topic}%")
            ->exists();

        $gaps = [];
        $opportunities = [];

        if (!$existing) {
            $gaps[] = "No content found for '{$topic}'";
            $opportunities[] = "Create a comprehensive guide about '{$topic}'";
        }

        $aiResponse = $this->aiGateway->generate([
            'system_prompt'   => "You are a content strategist. Identify content gaps and opportunities for the topic '{$topic}'.",
            'user_prompt'     => json_encode([
                'topic'            => $topic,
                'existing_content' => $existing ? 'Some content exists' : 'No content exists',
                'brand_id'         => $brandId,
            ]),
            'temperature'     => 0.5,
            'max_tokens'      => 1024,
            'response_format' => 'json',
        ]);

        $aiData = [];
        if ($aiResponse['success'] ?? false) {
            $aiData = json_decode($aiResponse['content'], true) ?? [];
        }

        return [
            'gaps'                 => $gaps,
            'opportunities'        => $opportunities,
            'ai_suggestions'       => $aiData['suggestions'] ?? [],
            'competitorCoverage'   => $aiData['competitor_coverage'] ?? [],
            'has_existing_content' => $existing,
        ];
    }

    public function generateOutline(string $topic, string $template = 'blog'): array
    {
        $response = $this->aiGateway->generate([
            'system_prompt'   => $this->getOutlineSystemPrompt($template),
            'user_prompt'     => json_encode([
                'topic'           => $topic,
                'template'        => $template,
                'style'           => 'professional, engaging, informative',
                'target_audience' => 'international travelers interested in African safaris and culture',
            ]),
            'temperature'     => 0.6,
            'max_tokens'      => 2048,
            'response_format' => 'json',
        ]);

        if (!($response['success'] ?? false)) {
            return $this->getFallbackOutline($topic, $template);
        }

        $data = json_decode($response['content'], true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            return $this->getFallbackOutline($topic, $template);
        }
        return $data;
    }

    protected function getOutlineSystemPrompt(string $template): string
    {
        $format = match ($template) {
            'blog'   => 'Blog post with H2/H3 headings, introduction, 3-5 main sections, conclusion',
            'social' => 'Social media post with hook, body, call-to-action',
            'email'  => 'Email with subject line, body paragraphs, CTAs',
            default  => 'Structured content with clear sections',
        };

        return <<<PROMPT
You are a content strategist for an African travel company.

Generate a detailed outline for a {$template} about the given topic.

Requirements:
- Follow the {$format} structure
- Include clear section titles
- Add bullet points for key sub-topics
- Suggest a target keyword
- Provide a meta description suggestion

Return ONLY valid JSON:
{
    "title": "Suggested title",
    "sections": [{"heading": "Section 1", "subsections": ["point 1", "point 2"]}],
    "target_keyword": "primary keyword",
    "meta_description": "SEO meta description (140-160 chars)",
    "estimated_word_count": 1200
}
PROMPT;
    }

    protected function getFallbackOutline(string $topic, string $template): array
    {
        $sections = [
            ["heading" => "Introduction", "subsections" => ["Hook the reader", "State the problem"]],
            ["heading" => "What is {$topic}?", "subsections" => ["Definition", "Key concepts"]],
            ["heading" => "Why {$topic} Matters", "subsections" => ["Benefits", "Examples"]],
            ["heading" => "How to Experience {$topic}", "subsections" => ["Step 1", "Step 2", "Step 3"]],
            ["heading" => "Common Mistakes", "subsections" => ["Pitfalls to avoid"]],
            ["heading" => "Conclusion", "subsections" => ["Summary", "Call to action"]],
        ];

        if ($template === 'social') {
            $sections = [
                ["heading" => "Hook", "subsections" => ["Attention-grabbing statement"]],
                ["heading" => "Body", "subsections" => ["Key points about {$topic}"]],
                ["heading" => "Call to Action", "subsections" => ["CTA to learn more"]],
            ];
        }

        return [
            'title'                => "The Ultimate Guide to {$topic}",
            'sections'             => $sections,
            'target_keyword'       => strtolower($topic),
            'meta_description'     => "Discover everything about {$topic} in this comprehensive guide.",
            'estimated_word_count' => $template === 'blog' ? 1500 : 300,
        ];
    }
}