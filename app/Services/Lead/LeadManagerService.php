<?php

namespace App\Services\Lead;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\LeadInteraction;
use App\Models\AiAction;
use App\Services\AI\AiGatewayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadManagerService
{
    protected AiGatewayService $aiGateway;

    public function __construct(AiGatewayService $aiGateway)
    {
        $this->aiGateway = $aiGateway;
    }

    /**
     * Create a new lead.
     */
    public function createLead(array $data, Brand $brand): Lead
    {
        $lead = Lead::create([
            'brand_id' => $brand->id,
            'campaign_id' => $data['campaign_id'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'source' => $data['source'] ?? 'website',
            'category' => $data['category'] ?? null,
            'status' => 'new',
            'metadata' => $data['metadata'] ?? null,
        ]);

        // Process lead with AI
        $this->processLeadWithAI($lead);

        Log::info('Lead created', [
            'lead_id' => $lead->id,
            'brand_id' => $brand->id,
            'email' => $lead->email,
            'category' => $lead->category,
        ]);

        return $lead;
    }

    /**
     * Process lead with AI (summarize, categorize, suggest response).
     */
    public function processLeadWithAI(Lead $lead): void
    {
        $brand = $lead->brand;

        // Build prompt based on category
        $prompt = $this->buildPrompt($lead, $brand);

        $response = $this->aiGateway->generate([
            'system_prompt' => $this->getLeadSystemPrompt($brand, $lead->category),
            'user_prompt' => json_encode($prompt, JSON_PRETTY_PRINT),
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'response_format' => 'json',
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $lead->update([
                    'ai_summary' => $data['summary'] ?? null,
                    'ai_suggested_response' => $data['suggested_response'] ?? null,
                    'category' => $lead->category ?? $data['category'] ?? null,
                    'estimated_value' => $data['estimated_value'] ?? null,
                    'score' => $data['score'] ?? null,
                    'ai_metadata' => array_merge(
                        $lead->ai_metadata ?? [],
                        [
                            'processed_at' => Carbon::now()->toDateTimeString(),
                            'provider' => $this->aiGateway->getProvider(),
                            'model' => $response['model_used'] ?? null,
                            'lead_type' => $lead->category,
                        ]
                    ),
                ]);

                // Create AI action for hot leads
                if (($data['score'] ?? 'cold') === 'hot' && ($data['estimated_value'] ?? 0) > 500) {
                    $this->createHotLeadAction($lead, $data);
                }

                Log::info('Lead processed with AI', [
                    'lead_id' => $lead->id,
                    'category' => $lead->category,
                    'score' => $data['score'] ?? null,
                ]);
            }
        } else {
            Log::error('AI processing failed for lead', [
                'lead_id' => $lead->id,
                'error' => $response['error'] ?? 'Unknown error',
            ]);
        }
    }

    /**
     * Build prompt based on lead category.
     */
    protected function buildPrompt(Lead $lead, Brand $brand): array
    {
        $base = [
            'brand' => [
                'name' => $brand->name,
                'domain' => $brand->domain_type,
                'voice' => $brand->brand_voice,
            ],
            'lead' => [
                'name' => trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? '')),
                'email' => $lead->email,
                'company' => $lead->company,
                'title' => $lead->title,
                'message' => $lead->message,
                'source' => $lead->source,
            ],
        ];

        // Add category-specific context
        if ($lead->category === 'travel') {
            $base['context'] = [
                'type' => 'Travel Booking',
                'help_text' => 'This is a travel inquiry. They want to book a safari, tour, or travel experience.',
                'fields' => $lead->metadata['booking_details'] ?? [],
            ];
        } elseif ($lead->category === 'software') {
            $base['context'] = [
                'type' => 'Software Engineering',
                'help_text' => 'This is a software inquiry. They want to hire for development, consulting, or technical work.',
                'fields' => $lead->metadata['booking_details'] ?? [],
            ];
        }

        return $base;
    }

    /**
     * Get category-specific system prompt.
     */
    protected function getLeadSystemPrompt(Brand $brand, ?string $category): string
    {
        $categorySpecific = '';
        $exampleValue = '';

        if ($category === 'travel') {
            $categorySpecific = <<<TRAVEL
This is a TRAVEL BOOKING inquiry. Focus on:
- Safari packages, tours, destinations
- Group sizes and preferences
- Dates and duration
- Budget and value
- Travel experience and recommendations

Estimated values for travel:
- Safari package: $2,000 - $10,000 per person
- Beach holiday: $1,000 - $5,000 per person
- Cultural tour: $500 - $3,000 per person
- Custom package: $5,000 - $20,000+
TRAVEL;
            $exampleValue = 3000;
        } elseif ($category === 'software') {
            $categorySpecific = <<<SOFTWARE
This is a SOFTWARE ENGINEERING inquiry. Focus on:
- Project type (web, mobile, API, custom)
- Technical requirements and stack
- Timeline and deliverables
- Budget and scope
- Business value and ROI

Estimated values for software:
- Web development: $5,000 - $50,000
- Mobile app: $10,000 - $100,000
- API development: $3,000 - $30,000
- Custom software: $15,000 - $150,000
- Technical consulting: $2,000 - $20,000
SOFTWARE;
            $exampleValue = 15000;
        }

        return <<<PROMPT
You are a lead qualification expert for {$brand->name}.

{$categorySpecific}

Analyze the lead information and provide:

1. A brief summary (2-3 sentences) of what this lead wants
2. Category: Travel, Software, SEO, Consulting, or Other (if not already set)
3. Lead score: hot, warm, or cold (consider budget, timeline, and need)
4. Estimated value (USD)
5. Suggested response (3-4 sentences)

Return ONLY valid JSON with this structure:
{
    "summary": "Brief summary of the lead's needs",
    "category": "travel|software|seo|consulting|other",
    "score": "hot|warm|cold",
    "estimated_value": {$exampleValue},
    "suggested_response": "Your suggested response text"
}
PROMPT;
    }

    /**
     * Create AI action for hot leads.
     */
    protected function createHotLeadAction(Lead $lead, array $data): void
    {
        AiAction::create([
            'brand_id' => $lead->brand_id,
            'brief_id' => null,
            'title' => "🔥 Follow up with hot lead: " . ($lead->first_name ?? 'Lead'),
            'category' => 'strategy',
            'description' => "High-value lead scored as 'hot' with estimated value of $" . ($data['estimated_value'] ?? 0) . ". Category: " . ($lead->category ?? 'Unknown') . ". Follow up immediately.",
            'estimated_impact' => $data['estimated_value'] ?? 500,
            'priority' => 5,
            'status' => 'pending',
        ]);
    }

    /**
     * Add interaction to a lead.
     */
    public function addInteraction(Lead $lead, string $type, string $content, ?string $notes = null): LeadInteraction
    {
        $interaction = LeadInteraction::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'content' => $content,
            'notes' => $notes,
        ]);

        $lead->update([
            'last_contacted_at' => Carbon::now(),
        ]);

        Log::info('Lead interaction added', [
            'lead_id' => $lead->id,
            'interaction_type' => $type,
        ]);

        return $interaction;
    }

    /**
     * Update lead status.
     */
    public function updateStatus(Lead $lead, string $status, ?string $notes = null): void
    {
        $lead->update([
            'status' => $status,
            'notes' => $notes ? ($lead->notes ? $lead->notes . "\n" : '') . $notes : $lead->notes,
        ]);

        $this->addInteraction(
            $lead,
            'note',
            "Status changed to: " . ucfirst($status),
            $notes
        );

        Log::info('Lead status updated', [
            'lead_id' => $lead->id,
            'status' => $status,
        ]);
    }

    /**
     * Get leads by status.
     */
    public function getLeadsByStatus(Brand $brand, string $status, int $limit = 50): array
    {
        return Lead::where('brand_id', $brand->id)
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get leads by category.
     */
    public function getLeadsByCategory(Brand $brand, ?string $category = null, int $limit = 50): array
    {
        $query = Lead::where('brand_id', $brand->id);

        if ($category) {
            $query->where('category', $category);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get lead statistics.
     */
    public function getLeadStats(Brand $brand): array
    {
        $total = Lead::where('brand_id', $brand->id)->count();
        $new = Lead::where('brand_id', $brand->id)->where('status', 'new')->count();
        $hot = Lead::where('brand_id', $brand->id)->where('score', 'hot')->count();
        $won = Lead::where('brand_id', $brand->id)->where('status', 'won')->count();
        $lost = Lead::where('brand_id', $brand->id)->where('status', 'lost')->count();

        $travelCount = Lead::where('brand_id', $brand->id)->where('category', 'travel')->count();
        $softwareCount = Lead::where('brand_id', $brand->id)->where('category', 'software')->count();

        $totalValue = Lead::where('brand_id', $brand->id)->sum('estimated_value');
        $wonValue = Lead::where('brand_id', $brand->id)->where('status', 'won')->sum('estimated_value');

        return [
            'total_leads' => $total,
            'new_leads' => $new,
            'hot_leads' => $hot,
            'won_leads' => $won,
            'lost_leads' => $lost,
            'travel_leads' => $travelCount,
            'software_leads' => $softwareCount,
            'total_value' => round($totalValue, 2),
            'won_value' => round($wonValue, 2),
            'conversion_rate' => $total > 0 ? round(($won / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get leads needing follow-up.
     */
    public function getLeadsNeedingFollowUp(Brand $brand, int $days = 3): array
    {
        return Lead::where('brand_id', $brand->id)
            ->where('status', '!=', 'won')
            ->where('status', '!=', 'lost')
            ->where(function ($query) use ($days) {
                $query->where('follow_up_at', '<=', Carbon::now()->addDays($days))
                    ->orWhereNull('follow_up_at');
            })
            ->orderBy('follow_up_at', 'asc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
 * Get a lead by ID.
 */
public function getLead(int $brandId, int $leadId): ?Lead
{
    return Lead::where('brand_id', $brandId)->find($leadId);
}

/**
 * Get lead engagement data.
 */
public function getLeadEngagement(int $brandId, int $leadId): array
{
    $lead = $this->getLead($brandId, $leadId);
    if (!$lead) {
        return ['activities' => 0, 'emailsOpened' => 0];
    }

    $interactions = $lead->interactions;
    return [
        'activities' => $interactions->count(),
        'emailsOpened' => $interactions->where('type', 'email_open')->count(),
        'last_activity' => $interactions->max('created_at'),
        'types' => $interactions->groupBy('type')->map->count(),
    ];
}

/**
 * Get lead context (notes, history, etc.)
 */
public function getLeadContext(int $brandId, int $leadId): array
{
    $lead = $this->getLead($brandId, $leadId);
    if (!$lead) {
        return ['notes' => 'Lead not found', 'history' => []];
    }

    return [
        'notes' => $lead->notes ?? 'No notes available',
        'history' => $lead->interactions()->orderBy('created_at', 'desc')->limit(10)->get(),
        'status' => $lead->status,
        'score' => $lead->score,
    ];
}

/**
 * Generate a follow-up message for a lead.
 */
public function generateFollowUpMessage(int $brandId, int $leadId): array
{
    $lead = $this->getLead($brandId, $leadId);
    if (!$lead) {
        return ['message' => 'Lead not found'];
    }

    // Use the existing AI processing or a simple template
    $message = "Hi {$lead->first_name},\n\n";
    $message .= "I noticed your interest in our services. ";
    $message .= "Would you like to schedule a call to discuss how we can help?\n\n";
    $message .= "Best,\nThe Vumbi Team";

    return [
        'message' => $message,
        'lead' => $lead,
    ];
}
}