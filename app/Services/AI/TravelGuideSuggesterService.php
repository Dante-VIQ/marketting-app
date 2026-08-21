<?php

namespace App\Services\AI;

use App\Models\Brand;
use App\Models\BlogPost;
use App\Models\TravelGuide;
use App\Models\AffiliateOffer;
use App\Services\AI\AiGatewayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TravelGuideSuggesterService
{
    protected AiGatewayService $aiGateway;

    public function __construct(AiGatewayService $aiGateway)
    {
        $this->aiGateway = $aiGateway;
    }

    /**
     * Suggest new travel guides based on blog content and affiliate offers.
     */
    public function suggestGuides(Brand $brand): array
    {
        // Get recent blogs
        $blogs = BlogPost::where('brand_id', $brand->id)
            ->where('status', 'published')
            ->orderBy('views', 'desc')
            ->limit(20)
            ->get();

        // Get affiliate offers
        $offers = AffiliateOffer::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->get();

        // Get existing guides
        $existingGuides = TravelGuide::where('brand_id', $brand->id)
            ->pluck('destination')
            ->toArray();

        // Build prompt
        $prompt = [
            'blogs' => $blogs->map(function ($blog) {
                return [
                    'title' => $blog->title,
                    'tags' => $blog->tags ?? [],
                    'categories' => $blog->categories ?? [],
                    'views' => $blog->views,
                    'excerpt' => substr($blog->excerpt ?? '', 0, 200),
                ];
            })->toArray(),
            'affiliate_offers' => $offers->map(function ($offer) {
                return [
                    'name' => $offer->name,
                    'category' => $offer->category,
                    'destination' => $offer->destination,
                    'price' => $offer->price,
                ];
            })->toArray(),
            'existing_guides' => $existingGuides,
        ];

        $response = $this->aiGateway->generate([
            'system_prompt' => $this->getGuideSystemPrompt(),
            'user_prompt' => json_encode($prompt, JSON_PRETTY_PRINT),
            'temperature' => 0.5,
            'max_tokens' => 4096,
            'response_format' => 'json',
        ]);

        if (!$response['success']) {
            Log::error('Travel guide suggestion failed', [
                'brand_id' => $brand->id,
                'error' => $response['error'] ?? 'Unknown error',
            ]);
            return [];
        }

        $data = json_decode($response['content'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse travel guide suggestion response', [
                'brand_id' => $brand->id,
                'response' => $response['content'],
            ]);
            return [];
        }

        // Process and create guide suggestions as AI actions
        return $this->createGuideActions($brand, $data['guides'] ?? []);
    }

    /**
     * Create AI actions for suggested guides.
     */
    protected function createGuideActions(Brand $brand, array $guides): array
    {
        $created = [];

        foreach ($guides as $guideData) {
            // Check if guide already exists
            $slug = Str::slug($guideData['title'] ?? $guideData['destination']);

            if (TravelGuide::where('brand_id', $brand->id)->where('slug', $slug)->exists()) {
                continue;
            }

            // Create an AI action to generate this guide
            $action = \App\Models\AiAction::create([
                'brand_id' => $brand->id,
                'brief_id' => null,
                'title' => "Create travel guide: " . ($guideData['title'] ?? $guideData['destination']),
                'category' => 'content',
                'description' => $guideData['description'] ?? 'Generate a comprehensive travel guide',
                'suggested_content' => json_encode([
                    'destination' => $guideData['destination'] ?? '',
                    'duration' => $guideData['duration'] ?? '3 days',
                    'tour_packages' => $guideData['tour_packages'] ?? [],
                    'affiliate_offers' => $guideData['affiliate_offers'] ?? [],
                    'outline' => $guideData['outline'] ?? [],
                ]),
                'estimated_impact' => $guideData['estimated_impact'] ?? 500,
                'priority' => 4,
                'status' => 'pending',
            ]);

            $created[] = [
                'action_id' => $action->id,
                'title' => $action->title,
                'destination' => $guideData['destination'] ?? '',
            ];
        }

        return $created;
    }

    /**
     * Get system prompt for travel guide suggester.
     */
    protected function getGuideSystemPrompt(): string
    {
        return <<<PROMPT
You are a travel content strategist. Your job is to suggest new travel guides based on blog performance and affiliate offers.

Rules:
1. Identify popular destinations from blogs and offers
2. Suggest guides that fill content gaps
3. Recommend duration and key attractions
4. Match with relevant affiliate offers
5. Return ONLY valid JSON

Response format:
{
    "guides": [
        {
            "title": "3 Days in Maasai Mara: The Ultimate Safari Guide",
            "destination": "Maasai Mara, Kenya",
            "duration": "3 days",
            "description": "A complete guide to experiencing the Maasai Mara in 3 days, including safari tips, accommodation, and best viewing spots.",
            "tour_packages": ["3-Day Maasai Mara Safari", "Luxury Mara Camp"],
            "affiliate_offers": [123, 456],
            "outline": ["Day 1: Arrival and Game Drive", "Day 2: Full Day Safari", "Day 3: Morning Game Drive and Departure"],
            "estimated_impact": 500
        }
    ]
}
PROMPT;
    }

    /**
     * Generate full content for a travel guide using AI.
     */
    public function generateGuideContent(TravelGuide $guide): string
    {
        $prompt = [
            'guide' => [
                'title' => $guide->title,
                'destination' => $guide->destination,
                'duration' => $guide->duration,
                'description' => $guide->description,
                'outline' => $guide->itinerary ?? [],
            ],
            'brand' => [
                'name' => $guide->brand->name,
                'voice' => $guide->brand->brand_voice,
            ],
        ];

        $response = $this->aiGateway->generate([
            'system_prompt' => $this->getContentGenerationPrompt(),
            'user_prompt' => json_encode($prompt, JSON_PRETTY_PRINT),
            'temperature' => 0.7,
            'max_tokens' => 8192,
        ]);

        if ($response['success']) {
            return $response['content'];
        }

        return '';
    }

    protected function getContentGenerationPrompt(): string
    {
        return <<<PROMPT
You are an expert travel writer. Write a detailed, engaging travel guide based on the provided outline.

Rules:
- Write in an engaging, informative tone
- Include practical tips (best time to visit, transport, accommodation)
- Incorporate affiliate offers naturally
- Add a call-to-action at the end
- Include day-by-day itinerary details
- Write at least 1500 words
PROMPT;
    }
}