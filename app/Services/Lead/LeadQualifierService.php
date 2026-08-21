<?php

namespace App\Services\Lead;

use App\Models\Lead;
use App\Models\Brand;
use App\Services\AI\AiGatewayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class LeadQualifierService
{
    protected AiGatewayService $aiGateway;
    protected LeadManagerService $leadManager;

    public function __construct(
        AiGatewayService $aiGateway,
        LeadManagerService $leadManager
    ) {
        $this->aiGateway = $aiGateway;
        $this->leadManager = $leadManager;
    }

    /**
     * Qualify a lead and identify missing information.
     */
    public function qualify(Lead $lead): array
    {
        $brand = $lead->brand;

        // Build the qualification prompt
        $prompt = $this->buildQualificationPrompt($lead, $brand);

        $response = $this->aiGateway->generate([
            'system_prompt' => $this->getQualificationSystemPrompt($brand, $lead->category),
            'user_prompt' => json_encode($prompt, JSON_PRETTY_PRINT),
            'temperature' => 0.3,
            'max_tokens' => 2048,
            'response_format' => 'json',
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                // Update lead with missing fields
                $lead->update([
                    'missing_fields' => $data['missing_fields'] ?? [],
                    'follow_up_status' => !empty($data['missing_fields']) ? 'pending' : 'complete',
                ]);

                // If there are missing fields, trigger follow-up
                if (!empty($data['missing_fields'])) {
                    $this->sendFollowUp($lead, $data['missing_fields'], $data['follow_up_message'] ?? null);
                }

                return $data;
            }
        }

        Log::error('Lead qualification failed', [
            'lead_id' => $lead->id,
            'error' => $response['error'] ?? 'Unknown error',
        ]);

        return ['missing_fields' => []];
    }

    /**
     * Build qualification prompt.
     */
    protected function buildQualificationPrompt(Lead $lead, Brand $brand): array
    {
        $fields = $this->getRequiredFields($lead->category);
        $providedFields = $this->getProvidedFields($lead);

        return [
            'brand' => [
                'name' => $brand->name,
                'category' => $lead->category,
            ],
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->full_name,
                'email' => $lead->email,
                'message' => $lead->message,
                'metadata' => $lead->metadata,
            ],
            'required_fields' => $fields,
            'provided_fields' => $providedFields,
            'category' => $lead->category,
        ];
    }

    /**
     * Get required fields for each category.
     */
    protected function getRequiredFields(?string $category): array
    {
        if ($category === 'travel') {
            return [
                'tour_type' => [
                    'label' => 'Tour Type',
                    'description' => 'What type of tour are you interested in? (safari, beach, cultural, etc.)',
                    'examples' => ['safari', 'beach holiday', 'cultural tour'],
                    'priority' => 'high',
                ],
                'number_of_people' => [
                    'label' => 'Number of People',
                    'description' => 'How many people will be traveling?',
                    'examples' => ['2 adults', '4 adults, 2 children'],
                    'priority' => 'high',
                ],
                'preferred_date' => [
                    'label' => 'Preferred Dates',
                    'description' => 'When would you like to travel?',
                    'examples' => ['October 2026', 'December 15-25, 2026'],
                    'priority' => 'high',
                ],
                'duration_days' => [
                    'label' => 'Duration',
                    'description' => 'How many days would you like for your tour?',
                    'examples' => ['5 days', '10 days', '2 weeks'],
                    'priority' => 'medium',
                ],
                'budget_range' => [
                    'label' => 'Budget Range',
                    'description' => 'What is your approximate budget for this trip?',
                    'examples' => ['$2,000 - $3,000', '$5,000+'],
                    'priority' => 'medium',
                ],
                'country' => [
                    'label' => 'Destination Country',
                    'description' => 'Which country or countries would you like to visit?',
                    'examples' => ['Kenya', 'Tanzania', 'Both'],
                    'priority' => 'medium',
                ],
            ];
        }

        if ($category === 'software') {
            return [
                'project_type' => [
                    'label' => 'Project Type',
                    'description' => 'What type of software project is this?',
                    'examples' => ['web development', 'mobile app', 'API development'],
                    'priority' => 'high',
                ],
                'project_description' => [
                    'label' => 'Project Description',
                    'description' => 'Can you describe your project in more detail?',
                    'examples' => ['Build a booking platform', 'Create a mobile app for tour operators'],
                    'priority' => 'high',
                ],
                'timeline' => [
                    'label' => 'Expected Timeline',
                    'description' => 'When do you need this completed?',
                    'examples' => ['2 months', 'Q4 2026'],
                    'priority' => 'high',
                ],
                'budget' => [
                    'label' => 'Budget Range',
                    'description' => 'What is your estimated budget for this project?',
                    'examples' => ['$5,000 - $10,000', '$20,000+'],
                    'priority' => 'medium',
                ],
                'current_stack' => [
                    'label' => 'Current Tech Stack',
                    'description' => 'What technologies are you currently using?',
                    'examples' => ['PHP, MySQL', 'React, Node.js'],
                    'priority' => 'low',
                ],
            ];
        }

        return [];
    }

    /**
     * Get fields that have been provided.
     */
    protected function getProvidedFields(Lead $lead): array
    {
        $provided = [];
        $metadata = $lead->metadata ?? [];

        // Check message for clues
        $message = $lead->message ?? '';

        if ($lead->category === 'travel') {
            if (!empty($metadata['tour_type'])) {
                $provided['tour_type'] = $metadata['tour_type'];
            } elseif (str_contains($message, 'safari') || str_contains($message, 'tour')) {
                $provided['tour_type'] = 'safari';
            }

            if (!empty($metadata['number_of_people'])) {
                $provided['number_of_people'] = $metadata['number_of_people'];
            } elseif (preg_match('/(\d+)\s*(people|persons|adults)/i', $message, $matches)) {
                $provided['number_of_people'] = $matches[1];
            }

            if (!empty($metadata['preferred_date'])) {
                $provided['preferred_date'] = $metadata['preferred_date'];
            } elseif (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4}|\d{1,2}\s+\w+\s+\d{4}|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2})/i', $message, $matches)) {
                $provided['preferred_date'] = $matches[0];
            }

            if (!empty($metadata['duration_days'])) {
                $provided['duration_days'] = $metadata['duration_days'];
            } elseif (preg_match('/(\d+)\s*(days|day)/i', $message, $matches)) {
                $provided['duration_days'] = $matches[1];
            }
        }

        if ($lead->category === 'software') {
            if (!empty($metadata['project_type'])) {
                $provided['project_type'] = $metadata['project_type'];
            } elseif (str_contains($message, 'website') || str_contains($message, 'web')) {
                $provided['project_type'] = 'web development';
            } elseif (str_contains($message, 'app') || str_contains($message, 'mobile')) {
                $provided['project_type'] = 'mobile app';
            }

            if (!empty($metadata['project_description'])) {
                $provided['project_description'] = $metadata['project_description'];
            } elseif (strlen($message) > 50) {
                $provided['project_description'] = substr($message, 0, 200);
            }

            if (!empty($metadata['timeline'])) {
                $provided['timeline'] = $metadata['timeline'];
            } elseif (preg_match('/(\d+)\s*(month|week)/i', $message, $matches)) {
                $provided['timeline'] = $matches[0];
            }
        }

        return $provided;
    }

    /**
     * Get qualification system prompt.
     */
    protected function getQualificationSystemPrompt(Brand $brand, ?string $category): string
    {
        return <<<PROMPT
You are a lead qualification assistant for {$brand->name}.

Analyze the lead information and determine:
1. What information is missing from the lead
2. What follow-up questions to ask

The lead is a {$category} inquiry.

Return ONLY valid JSON with this structure:
{
    "missing_fields": [
        {
            "field": "field_name",
            "question": "The question to ask the client",
            "priority": "high|medium|low"
        }
    ],
    "follow_up_message": "A friendly message to send to the client about completing their inquiry"
}
PROMPT;
    }

    /**
     * Send follow-up email.
     */
    public function sendFollowUp(Lead $lead, array $missingFields, ?string $message = null): void
    {
        // Check if we should send follow-up
        if ($lead->follow_up_count >= 3) {
            Log::info('Max follow-up attempts reached', ['lead_id' => $lead->id]);
            return;
        }

        // Build follow-up email content
        $content = $this->buildFollowUpContent($lead, $missingFields, $message);

        try {
            // Send email
            Mail::send('emails.follow-up', [
                'lead' => $lead,
                'content' => $content,
                'missing_fields' => $missingFields,
            ], function ($mail) use ($lead) {
                $mail->to($lead->email)
                    ->subject('Complete Your Inquiry - Vumbi Ventures');
            });

            // Update lead
            $lead->update([
                'follow_up_sent_at' => Carbon::now(),
                'follow_up_count' => $lead->follow_up_count + 1,
                'follow_up_status' => 'waiting',
                'follow_up_history' => array_merge(
                    $lead->follow_up_history ?? [],
                    [
                        [
                            'sent_at' => Carbon::now()->toDateTimeString(),
                            'fields' => array_column($missingFields, 'field'),
                            'message' => $message,
                        ]
                    ]
                ),
            ]);

            Log::info('Follow-up sent', [
                'lead_id' => $lead->id,
                'email' => $lead->email,
                'missing_fields' => count($missingFields),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send follow-up email', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build follow-up content.
     */
    protected function buildFollowUpContent(Lead $lead, array $missingFields, ?string $message = null): string
    {
        $parts = [];
        $parts[] = "Hello " . ($lead->first_name ?? 'there') . ",";
        $parts[] = "";
        $parts[] = "Thank you for your interest in Vumbi Ventures! We received your inquiry and we're excited to help you.";
        $parts[] = "";
        $parts[] = "To provide you with the best possible service, we need a few more details:";

        foreach ($missingFields as $field) {
            $parts[] = "";
            $parts[] = "🔹 **" . ($field['label'] ?? $field['field']) . "**";
            $parts[] = "   " . ($field['question'] ?? $field['description'] ?? 'Please provide this information.');
        }

        if ($message) {
            $parts[] = "";
            $parts[] = $message;
        }

        $parts[] = "";
        $parts[] = "You can reply directly to this email with the requested information.";
        $parts[] = "Or click the link below to update your inquiry online:";
        $parts[] = "";
        $parts[] = "👉 " . route('leads.update', ['lead' => $lead->id, 'token' => $this->generateToken($lead)]);
        $parts[] = "";
        $parts[] = "We're looking forward to helping you!";
        $parts[] = "";
        $parts[] = "Best regards,";
        $parts[] = "Vumbi Ventures Team";

        return implode("\n", $parts);
    }

    /**
     * Generate a secure token for lead updates.
     */
    protected function generateToken(Lead $lead): string
    {
        return hash_hmac('sha256', $lead->id . $lead->email, config('app.key'));
    }

    /**
     * Process follow-up response.
     */
    public function processResponse(Lead $lead, array $data): void
    {
        // Update lead with new data
        $updatedFields = [];

        foreach ($data as $key => $value) {
            if (in_array($key, array_column($lead->missing_fields ?? [], 'field'))) {
                // Store the updated field
                $updatedFields[$key] = $value;

                // If it's a metadata field, update metadata
                if (str_starts_with($key, 'metadata.')) {
                    $metadataKey = str_replace('metadata.', '', $key);
                    $metadata = $lead->metadata ?? [];
                    $metadata[$metadataKey] = $value;
                    $lead->metadata = $metadata;
                }
            }
        }

        // Update lead
        $lead->update([
            'follow_up_status' => 'responded',
            'follow_up_responded_at' => Carbon::now(),
            'follow_up_response' => json_encode($data),
            'updated_fields' => $updatedFields,
        ]);

        // Re-qualify the lead with new information
        $this->qualify($lead);

        // Log the interaction
        $this->leadManager->addInteraction(
            $lead,
            'email',
            "Follow-up response received:\n" . json_encode($data, JSON_PRETTY_PRINT),
            'Client responded to follow-up with additional information.'
        );

        Log::info('Follow-up response processed', [
            'lead_id' => $lead->id,
            'updated_fields' => array_keys($updatedFields),
        ]);
    }

    /**
     * Check for leads that need follow-up.
     */
    public function checkForFollowUps(Brand $brand): void
    {
        $leads = Lead::where('brand_id', $brand->id)
            ->where('follow_up_status', 'pending')
            ->where('follow_up_count', '<', 3)
            ->where(function ($query) {
                $query->whereNull('follow_up_sent_at')
                    ->orWhere('follow_up_sent_at', '<=', Carbon::now()->subHours(24));
            })
            ->get();

        foreach ($leads as $lead) {
            $this->qualify($lead);
        }
    }
}