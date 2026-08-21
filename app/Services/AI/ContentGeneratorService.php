<?php

namespace App\Services\AI;

use App\Models\AiAction;
use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\GuardianAuditLog;
use App\Models\KnowledgeBase;
use App\Services\AI\AiGatewayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Scanner\PageScannerService;

class ContentGeneratorService
{
    protected PageScannerService $scanner;

    public function __construct(AiGatewayService $aiGateway, PageScannerService $scanner)
    {
        $this->aiGateway = $aiGateway;
        $this->scanner = $scanner;
    }

    /**
     * Generate content for an approved action.
     */
    public function generateForAction(AiAction $action): ?ContentDraft
    {
        if ($action->status !== 'approved') {
            Log::warning('ContentGenerator: Action is not approved', [
                'action_id' => $action->id,
                'status'    => $action->status,
            ]);
            return null;
        }

        $brand = $action->brand;

        if (!$brand) {
            Log::error('ContentGenerator: Associated brand not found for action', [
                'action_id' => $action->id,
            ]);
            return null;
        }

        if ($action->target_url) {
            try {
                $snapshot = $this->scanner->scanPage($action->target_url, $brand, $action);

                // Add snapshot data to prompt
                $promptData['page_snapshot'] = [
                    'url' => $snapshot->url,
                    'title' => $snapshot->title,
                    'headings' => $snapshot->headings,
                    'topics_covered' => $snapshot->topics_covered,
                    'content_gaps' => $snapshot->content_gaps,
                    'recommendations' => $snapshot->recommendations,
                    'word_count' => $snapshot->word_count,
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to scan page, proceeding without snapshot', [
                    'action_id' => $action->id,
                    'url' => $action->target_url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        // Check if content draft already exists
        $existing = ContentDraft::where('action_id', $action->id)->first();
        if ($existing) {
            Log::info('ContentGenerator: Draft already exists', [
                'action_id' => $action->id,
                'draft_id'  => $existing->id,
            ]);
            return $existing;
        }

        $promptData = $this->buildPrompt($action, $brand);

        // Call AI Gateway
        $response = $this->aiGateway->generate([
            'system_prompt'   => $this->getSystemPrompt($brand, $action->category),
            'user_prompt'     => json_encode($promptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
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

        // Clean & Parse response payload with validation
        $contentData = $this->parseContentResponse($response['content'], $action->category);

        // Persist Draft and update Action status atomically
        try {
            return DB::transaction(function () use ($action, $brand, $contentData, $promptData, $response) {
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
                        'provider'    => $this->aiGateway->getProvider(),
                        'model'       => $response['model_used'] ?? null,
                        'tokens_used' => $response['tokens_used'] ?? 0,
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
     * Get content type based on category.
     */
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

    /**
     * Build prompt data structure.
     */
    public function buildPrompt(AiAction $action, Brand $brand): array
    {
        $knowledge = KnowledgeBase::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->pluck('content', 'key')
            ->toArray();

        return [
            'brand' => [
                'name'   => $brand->name,
                'voice'  => $brand->brand_voice ?? 'Professional and engaging',
                'domain' => $brand->domain_type ?? 'digital business',
            ],
            'action' => [
                'title'           => $action->title,
                'description'     => $action->description,
                'category'        => $action->category,
                'target_platform' => $action->target_platform,
                'target_url'      => $action->target_url,
            ],
            'knowledge_base' => $knowledge,
            'requirements'   => [
                'length' => $this->getLengthRequirement($action->category),
                'tone'   => 'professional yet approachable',
                'format' => $this->getFormatRequirement($action->category),
            ],
        ];
    }

    /**
     * Get length requirement based on category.
     */
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

    /**
     * Get format requirement based on category.
     */
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

    /**
     * Get the system prompt for content generation.
     */
    public function getSystemPrompt(Brand $brand, string $category): string
    {
        $tone = $brand->brand_voice ?? 'Professional, clear, and compelling';
        $name = $brand->name;

        // SEO-specific strict instructions
        if ($category === 'seo') {
            return <<<PROMPT
You are an SEO expert for {$name}.

CRITICAL: This is a META DESCRIPTION action. Generate ONLY a meta description.

REQUIREMENTS:
- **EXACTLY 140-160 characters total**
- Include the target keyword naturally
- Be compelling and click-worthy
- DO NOT write a blog post
- DO NOT write headings or paragraphs
- DO NOT write more than 160 characters

Output ONLY valid JSON:
{
    "title": "Page title (50-60 chars)",
    "meta_title": "SEO meta title (50-60 chars)",
    "meta_description": "Your 140-160 character meta description here",
    "target_keyword": "The target keyword"
}
PROMPT;
        }

        // Category-specific instructions for non-SEO content
        $categoryInstructions = match ($category) {
            'content' => "You are generating a full blog post.\n\nREQUIREMENTS:\n- 1200-2000 words\n- Clear structure with H2, H3 headings\n- Include a strong introduction and conclusion\n- Use bullet points and lists where appropriate\n- Include internal linking suggestions",
            'social' => "You are generating a social media post.\n\nREQUIREMENTS:\n- 100-200 words\n- Engaging and conversational tone\n- Include relevant hashtags\n- Add a call-to-action",
            'email' => "You are generating an email.\n\nREQUIREMENTS:\n- 300-500 words\n- Compelling subject line (40-60 chars)\n- Clear body structure\n- Strong call-to-action",
            'web_copy' => "You are generating web copy (landing page or section).\n\nREQUIREMENTS:\n- 500-800 words\n- Clear headlines and subheadings\n- Persuasive and conversion-focused\n- Include a call-to-action",
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

    /**
     * Parse AI content response with validation.
     */
    protected function parseContentResponse(string $response, string $category): array
    {
        try {
            // Step 1: Strip markdown code fences
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($response));
            
            // Step 2: Direct JSON parsing
            $data = json_decode($cleaned, true);
            
            // Step 3: String boundary search extraction if direct JSON parsing fails
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
            
            // Step 4: Robust fallback if JSON decoding ultimately fails
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::warning('ContentGenerator: Final JSON decode failed, defaulting to raw response fallback', [
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

            // Step 5: Process and sanitize SEO fields
            if ($category === 'seo') {
                $metaDesc  = $data['meta_description'] ?? '';
                $metaTitle = $data['meta_title'] ?? '';

                if (mb_strlen($metaDesc) > 160) {
                    Log::warning('ContentGenerator: SEO meta description truncated', [
                        'original_length' => mb_strlen($metaDesc),
                    ]);
                    $data['meta_description'] = mb_substr($metaDesc, 0, 157) . '...';
                }

                if (mb_strlen($metaTitle) > 60) {
                    Log::warning('ContentGenerator: SEO meta title truncated', [
                        'original_length' => mb_strlen($metaTitle),
                    ]);
                    $data['meta_title'] = mb_substr($metaTitle, 0, 57) . '...';
                }

                // Standardize content key for SEO category
                $data['content'] = $data['meta_description'] ?? $data['meta_title'] ?? 'SEO meta content';
            }

            // Step 6: Fallback check for missing body keys in non-SEO content
            if (empty($data['content']) && $category !== 'seo') {
                $data['content'] = $data['body'] ?? $data['text'] ?? $data['article'] ?? '';
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('ContentGenerator: Error parsing AI content response', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

    /**
     * Audit log helper.
     */
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
}
