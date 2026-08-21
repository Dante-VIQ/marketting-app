<?php

namespace App\Services\AI;

use App\Models\Brand;
use App\Models\BlogPost;
use App\Models\AffiliateOffer;
use App\Models\BlogAffiliatePlacement;
use App\Services\AI\AiGatewayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AffiliateLinkSuggesterService
{
    protected AiGatewayService $aiGateway;

    public function __construct(AiGatewayService $aiGateway)
    {
        $this->aiGateway = $aiGateway;
    }

    /**
     * Analyze a blog post and suggest affiliate offers.
     */
    public function suggestForBlogPost(BlogPost $post): array
    {
        $brand = $post->brand;

        // Get available affiliate offers for this brand
        $offers = AffiliateOffer::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->get();

        if ($offers->isEmpty()) {
            return ['suggestions' => [], 'message' => 'No active affiliate offers found.'];
        }

        // Build prompt for AI
        $prompt = [
            'blog' => [
                'title' => $post->title,
                'content' => substr($post->content ?? '', 0, 3000),
                'excerpt' => $post->excerpt,
                'tags' => $post->tags ?? [],
                'categories' => $post->categories ?? [],
            ],
            'offers' => $offers->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'description' => $offer->description,
                    'category' => $offer->category,
                    'destination' => $offer->destination,
                    'price' => $offer->price,
                    'commission' => $offer->commission_display,
                ];
            })->toArray(),
        ];

        // Get AI suggestions
        $response = $this->aiGateway->generate([
            'system_prompt' => $this->getSuggesterSystemPrompt(),
            'user_prompt' => json_encode($prompt, JSON_PRETTY_PRINT),
            'temperature' => 0.3,
            'max_tokens' => 2048,
            'response_format' => 'json',
        ]);

        if (!$response['success']) {
            Log::error('Affiliate suggestion failed', [
                'post_id' => $post->id,
                'error' => $response['error'] ?? 'Unknown error',
            ]);
            return ['suggestions' => [], 'message' => 'AI suggestion failed.'];
        }

        $data = json_decode($response['content'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse affiliate suggestion response', [
                'post_id' => $post->id,
                'response' => $response['content'],
            ]);
            return ['suggestions' => [], 'message' => 'Failed to parse AI response.'];
        }

        // Process and save suggestions
        $suggestions = $this->processSuggestions($post, $data['suggestions'] ?? []);

        return [
            'suggestions' => $suggestions,
            'message' => $data['message'] ?? 'Suggestions generated successfully.',
        ];
    }

    /**
     * Process AI suggestions and create placement records.
     */
    protected function processSuggestions(BlogPost $post, array $suggestions): array
    {
        $created = [];

        foreach ($suggestions as $suggestion) {
            if (empty($suggestion['offer_id'])) {
                continue;
            }

            $offer = AffiliateOffer::find($suggestion['offer_id']);
            if (!$offer) {
                continue;
            }

            // Check if placement already exists
            $existing = BlogAffiliatePlacement::where('blog_post_id', $post->id)
                ->where('affiliate_offer_id', $offer->id)
                ->first();

            if ($existing) {
                continue;
            }

            // Create placement
            $placement = BlogAffiliatePlacement::create([
                'blog_post_id' => $post->id,
                'affiliate_offer_id' => $offer->id,
                'placement_type' => $suggestion['placement_type'] ?? 'in_content',
                'anchor_text' => $suggestion['anchor_text'] ?? $offer->name,
                'url' => $offer->url,
                'metadata' => [
                    'reason' => $suggestion['reason'] ?? 'AI suggested',
                    'confidence' => $suggestion['confidence'] ?? 0.5,
                    'position' => $suggestion['position'] ?? null,
                ],
            ]);

            $created[] = [
                'placement_id' => $placement->id,
                'offer_name' => $offer->name,
                'placement_type' => $placement->placement_type,
                'anchor_text' => $placement->anchor_text,
                'reason' => $suggestion['reason'] ?? 'AI suggested',
            ];
        }

        return $created;
    }

    /**
     * Get system prompt for affiliate suggester.
     */
    protected function getSuggesterSystemPrompt(): string
    {
        return <<<PROMPT
You are an affiliate marketing expert. Your job is to analyze a blog post and suggest the best affiliate offers to place in it.

Rules:
1. Only suggest offers that are relevant to the blog post content
2. Match offers based on keywords, destinations, and categories
3. Suggest the placement type (in_content, sidebar, banner, cta)
4. Provide a reason for each suggestion
5. Return ONLY valid JSON

Response format:
{
    "suggestions": [
        {
            "offer_id": 1,
            "placement_type": "in_content",
            "anchor_text": "Book this safari now",
            "reason": "This offer matches the destination mentioned in the post",
            "confidence": 0.9,
            "position": "after_paragraph_3"
        }
    ],
    "message": "Suggestions generated successfully"
}
PROMPT;
    }

    /**
     * Batch suggest for all published blog posts.
     */
    public function suggestForAllBlogs(Brand $brand): array
    {
        $posts = BlogPost::where('brand_id', $brand->id)
            ->where('status', 'published')
            ->get();

        $results = [];

        foreach ($posts as $post) {
            // Skip if it already has placements
            if (BlogAffiliatePlacement::where('blog_post_id', $post->id)->exists()) {
                continue;
            }

            $results[$post->id] = $this->suggestForBlogPost($post);
        }

        return $results;
    }
}