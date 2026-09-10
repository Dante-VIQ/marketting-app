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
use App\Services\Scanner\PageScannerService;
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

    public function __construct(
        AiGatewayService $aiGateway,
        PageScannerService $scanner,
        ContentServicePolicy $contentPolicy
    ) {
        $this->aiGateway = $aiGateway;
        $this->scanner = $scanner;
        $this->contentPolicy = $contentPolicy;
    }

    /**
     * Generate content for an approved action.
     *
     * @param AiAction $action
     * @param User|null $user  If provided, authorization is enforced for the user.
     *                         If null, this is a system/agent call (already authenticated via API key).
     * @throws AuthorizationException
     */
    public function generateForAction(AiAction $action, ?User $user = null): ?ContentDraft
    {
        // 🔒 If a user is provided (human-triggered), authorize them
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

        // Action must be approved
        if ($action->status !== 'approved') {
            Log::warning('ContentGenerator: Action is not approved', [
                'action_id' => $action->id,
                'status'    => $action->status,
            ]);
            return null;
        }

        $brand = $action->brand;

        if (!$brand) {
            Log::error('ContentGenerator: Associated brand not found', [
                'action_id' => $action->id,
            ]);
            return null;
        }

        // 🔒 Content Service Policy – rate limits + blocked topics + output validation
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

        // Check if draft already exists
        $existing = ContentDraft::where('action_id', $action->id)->first();
        if ($existing) {
            Log::info('ContentGenerator: Draft already exists', [
                'action_id' => $action->id,
                'draft_id'  => $existing->id,
            ]);
            return $existing;
        }

        // Fetch the latest page snapshot for the target URL (if any)
        $pageSnapshot = null;
        if ($action->target_url) {
            $pageSnapshot = PageSnapshot::where('brand_id', $brand->id)
                ->where('url', 'like', '%' . $action->target_url . '%')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($pageSnapshot) {
                Log::info('ContentGenerator: Found page snapshot', [
                    'action_id'   => $action->id,
                    'snapshot_id' => $pageSnapshot->id,
                    'url'         => $pageSnapshot->url,
                    'word_count'  => $pageSnapshot->word_count,
                ]);
            } else {
                Log::info('ContentGenerator: No page snapshot found for target URL', [
                    'action_id'  => $action->id,
                    'target_url' => $action->target_url,
                ]);
            }
        }

        $promptData = $this->buildPrompt($action, $brand, $pageSnapshot);

        // Call AI Gateway
        $response = $this->aiGateway->generate([
            'system_prompt'   => $this->getSystemPrompt($brand, $action->category, $pageSnapshot),
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

        // Clean & parse response payload with validation
        $contentData = $this->parseContentResponse($response['content'], $action->category);

        // ✅ Validate output before saving
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

        // Persist draft and update action status atomically
        try {
            return DB::transaction(function () use (
                $action,
                $brand,
                $contentData,
                $promptData,
                $response,
                $pageSnapshot
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
                        'provider'         => $this->aiGateway->getProvider(),
                        'model'            => $response['model_used'] ?? null,
                        'tokens_used'      => $response['tokens_used'] ?? 0,
                        'page_snapshot_id' => $pageSnapshot?->id,
                    ],
                ]);

                $action->update(['status' => 'content_generated']);

                $this->logToGuardian($brand->id, $action->id, $promptData, $response);

                Log::info('ContentGenerator: Draft created successfully', [
                    'action_id' => $action->id,
                    'draft_id'  => $draft->id,
                    'type'      => $draft->type,
                ]);

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
     * Generate content for a brand directly (for agent API or human trigger).
     *
     * @throws \Exception
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

        // Create the action with status 'approved'
        $action = AiAction::create([
            'brand_id'        => $brandId,
            'title'           => 'Generate content: ' . substr($topic, 0, 100),
            'description'     => "Generated by agent for topic: {$topic}",
            'category'        => $category,
            'target_platform' => $template,
            'target_keyword'  => $topic,
            'status'          => 'approved',
        ]);

        // Pass the user through so authorization is enforced
        $draft = $this->generateForAction($action, $user);

        if (!$draft) {
            throw new \Exception('Content generation failed.');
        }

        return $draft;
    }

    // ============================================================
    // Existing helper methods (unchanged)
    // ============================================================

    protected function buildPrompt(AiAction $action, Brand $brand, ?PageSnapshot $snapshot = null): array
    {
        $knowledge = KnowledgeBase::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->pluck('content', 'key')
            ->toArray();

        $prompt = [
            'brand' => [
                'name'    => $brand->name,
                'voice'   => $brand->brand_voice ?? 'Professional and engaging',
                'domain'  => $brand->domain_type ?? 'digital business',
                'website' => $brand->website_url ?? '',
            ],
            'action' => [
                'title'           => $action->title,
                'description'     => $action->description,
                'category'        => $action->category,
                'target_platform' => $action->target_platform,
                'target_url'      => $action->target_url,
            ],
            'knowledge_base' => $knowledge,
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

            if ($action->category === 'seo') {
                $prompt['page_snapshot']['seo_guidance'] = [
                    'current_meta_title'       => $snapshot->meta_title,
                    'current_meta_description' => $snapshot->meta_description,
                    'target_keyword'           => $action->target_keyword ?? $snapshot->target_keyword ?? null,
                    'page_headings'            => $snapshot->headings,
                ];
            }
        }

        return $prompt;
    }

    protected function getSystemPrompt(Brand $brand, string $category, ?PageSnapshot $snapshot = null): string
    {
        $tone = $brand->brand_voice ?? 'Professional, clear, and compelling';
        $name = $brand->name;

        if ($category === 'seo') {
            $pageContext = '';
            if ($snapshot) {
                $pageContext = sprintf(
                    "The page being optimized is: %s\n",
                    $snapshot->title ?? $snapshot->url ?? 'Unknown page'
                );
                if ($snapshot->headings && isset($snapshot->headings['h1'])) {
                    $pageContext .= sprintf("Page H1: %s\n", $snapshot->headings['h1']);
                }
                if ($snapshot->topics_covered) {
                    $pageContext .= sprintf(
                        "Topics on this page: %s\n",
                        implode(', ', array_slice($snapshot->topics_covered, 0, 10))
                    );
                }
                if ($snapshot->meta_title) {
                    $pageContext .= sprintf("Current meta title: %s\n", $snapshot->meta_title);
                }
                if ($snapshot->meta_description) {
                    $pageContext .= sprintf("Current meta description: %s\n", $snapshot->meta_description);
                }
            }

            return <<<PROMPT
You are an SEO expert for {$name}.

{$pageContext}

CRITICAL: This is a META DESCRIPTION action. Generate ONLY a meta description based on the actual page content.

REQUIREMENTS:
- **EXACTLY 140-160 characters total**
- Include the target keyword naturally
- Be compelling and click-worthy
- Match the actual content of the page (use the page context above)
- DO NOT write a blog post
- DO NOT write headings or paragraphs
- DO NOT write more than 160 characters

Output ONLY valid JSON:
{
    "title": "Page title (50-60 chars)",
    "meta_title": "SEO meta title (50-60 chars)",
    "meta_description": "Your 140-160 character meta description based on page content",
    "target_keyword": "The target keyword"
}
PROMPT;
        }

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
                "You are generating a full blog post.\n\nREQUIREMENTS:\n- 1200-2000 words\n- Clear structure with H2, H3 headings\n- Include a strong introduction and conclusion\n- Use bullet points and lists where appropriate\n- Include internal linking suggestions\n%s",
                $pageContext
            ),
            'social' => sprintf(
                "You are generating a social media post.\n\nREQUIREMENTS:\n- 100-200 words\n- Engaging and conversational tone\n- Include relevant hashtags\n- Add a call-to-action\n%s",
                $pageContext
            ),
            'email' => sprintf(
                "You are generating an email.\n\nREQUIREMENTS:\n- 300-500 words\n- Compelling subject line (40-60 chars)\n- Clear body structure\n- Strong call-to-action\n%s",
                $pageContext
            ),
            'web_copy' => sprintf(
                "You are generating web copy (landing page or section).\n\nREQUIREMENTS:\n- 500-800 words\n- Clear headlines and subheadings\n- Persuasive and conversion-focused\n- Include a call-to-action\n%s",
                $pageContext
            ),
            default => "You are generating general content.\n\nREQUIREMENTS:\n- 500-800 words\n- Clear structure\n- Engaging and informative",
        };

        return <<<PROMPT
You are an expert content writer for {$name}.

Writing Style: "{$tone}"

{$categoryInstructions}

You must:
1. Follow the exact requirements for the content type
2. Return ONLY valid JSON
3. Do not add extra content beyond what's requested

Output Schema:
{
    "title": "The article title",
    "content": "The full content with proper formatting",
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
            'seo'      => 'Meta description only. 140-160 characters. Do NOT write a blog post.',
            'content'  => 'Blog post with clear markdown headings (H2, H3), bullet points, and a strong conclusion.',
            'social'   => 'Engaging social media post with hashtags and appropriate emojis.',
            'email'    => 'Email copy including Subject Line, Preview Text, Body, and Call To Action.',
            'web_copy' => 'Structured landing page copy with headlines, feature benefits, and conversion CTAs.',
            'campaign' => 'Promotional email with compelling offer hooks and actionable CTAs.',
            default    => 'Well-structured markdown formatted content.',
        };
    }

    protected function parseContentResponse(string $response, string $category): array
    {
        try {
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($response));
            $data = json_decode($cleaned, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('ContentGenerator: JSON parse failed, attempting boundary extraction', [
                    'error' => json_last_error_msg(),
                ]);

                $firstBrace = strpos($cleaned, '{');
                $lastBrace  = strrpos($cleaned, '}');

                if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                    $jsonCandidate = substr($cleaned, $firstBrace, ($lastBrace - $firstBrace) + 1);
                    $data = json_decode($jsonCandidate, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::info('ContentGenerator: JSON extracted successfully via boundaries');
                    }
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::warning('ContentGenerator: Final JSON decode failed, defaulting to raw response', [
                    'error' => json_last_error_msg(),
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
            Log::error('ContentGenerator: Error parsing AI content response', [
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

        $aiPrompt = [
            'topic'            => $topic,
            'existing_content' => $existing ? 'Some content exists' : 'No content exists',
            'brand_id'         => $brandId,
        ];

        $aiResponse = $this->aiGateway->generate([
            'system_prompt'   => "You are a content strategist. Identify content gaps and opportunities for the topic '{$topic}'.",
            'user_prompt'     => json_encode($aiPrompt),
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
        $prompt = [
            'topic'           => $topic,
            'template'        => $template,
            'style'           => 'professional, engaging, informative',
            'target_audience' => 'solo founders and small business owners',
        ];

        $response = $this->aiGateway->generate([
            'system_prompt'   => $this->getOutlineSystemPrompt($template),
            'user_prompt'     => json_encode($prompt),
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
            'blog'   => 'Blog post with H2/H3 headings, introduction, 3-5 main sections, and conclusion',
            'social' => 'Social media post with hook, body, and call-to-action',
            'email'  => 'Email with subject line, body paragraphs, and CTAs',
            default  => 'Structured content with clear sections',
        };

        return <<<PROMPT
You are a content strategist. Generate a detailed outline for a {$template} about the given topic.

Requirements:
- Follow the {$format} structure
- Include clear section titles
- Add bullet points for key sub-topics
- Suggest a target keyword
- Provide a meta description suggestion

Return ONLY valid JSON with this structure:
{
    "title": "Suggested title",
    "sections": [
        {"heading": "Section 1", "subsections": ["point 1", "point 2"]}
    ],
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
            ["heading" => "How to Implement {$topic}", "subsections" => ["Step 1", "Step 2", "Step 3"]],
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
            'meta_description'     => "Learn everything about {$topic} in this comprehensive guide.",
            'estimated_word_count' => $template === 'blog' ? 1500 : 300,
        ];
    }
}