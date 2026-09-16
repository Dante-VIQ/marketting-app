<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateContentForActionJob;
use App\Models\ActionVerification;
use App\Models\AgentExperience;
use App\Models\AgentOpportunityTracking;
use App\Models\AiAction;
use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\ConfidenceCalibration;
use App\Models\Lead;
use App\Models\SeoIssue;
use App\Services\AI\AiGatewayService;
use App\Services\AI\ContentGeneratorService;
use App\Services\AI\SeoAssistantService;
use App\Services\DataCollectionService;
use App\Services\Lead\LeadManagerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    protected $aiGateway;
    protected $seoAssistant;
    protected $leadService;
    protected $contentGenerator;

    protected DataCollectionService $dataCollection;

    public function __construct(
        AiGatewayService $aiGateway,
        SeoAssistantService $seoAssistant,
        LeadManagerService $leadService,
        ContentGeneratorService $contentGenerator,
        DataCollectionService $dataCollection
    ) {
        $this->aiGateway = $aiGateway;
        $this->seoAssistant = $seoAssistant;
        $this->leadService = $leadService;
        $this->contentGenerator = $contentGenerator;
        $this->dataCollection = $dataCollection;
    }



    /**
     * Check if today's data is fresh for a brand.
     * GET /api/agent/data-status/{brandId}
     */
    public function dataStatus($brandId)
    {
        try {
            $freshness = $this->dataCollection->isFresh((int) $brandId);

            return response()->json([
                'success'   => true,
                'brand_id'  => $brandId,
                'freshness' => $freshness,
                'all_fresh' => !in_array(false, $freshness, true),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger data collection for a brand.
     * POST /api/agent/refresh-data/{brandId}
     */
    public function refreshData($brandId)
    {
        try {
            $result = $this->dataCollection->ensureFreshData((int) $brandId);

            return response()->json([
                'success'   => true,
                'brand_id'  => $brandId,
                'queued'    => $result['queued'] ?? [],
                'message'   => $result['message'] ?? 'Collection triggered',
                'freshness' => $this->dataCollection->isFresh((int) $brandId),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    // ============= OPPORTUNITIES =============

    public function getOpportunities($brandId)
    {
        $brand = Brand::findOrFail($brandId);

        // Collect all opportunities
        $opportunities = [];

        // SEO issues
        $seoIssues = SeoIssue::where('brand_id', $brandId)
            ->where('status', 'open')
            ->limit(20)
            ->get();

        foreach ($seoIssues as $issue) {
            $opportunities[] = [
                'id' => $issue->id,
                'type' => 'seo_issue',
                'severity' => $issue->severity ?? 'medium',
                'title' => $issue->type ?? 'SEO Issue',
                'description' => $issue->description ?? 'SEO issue detected',
                'source' => 'seo_monitor',
                'detectedAt' => $issue->created_at->toISOString(),
                'payload' => [
                    'page' => $issue->page,
                    'issue_type' => $issue->type,
                    'status' => $issue->status,
                ],
                'impact' => $issue->severity === 'high' ? 500 : 100,
                'requires_approval' => $issue->severity === 'high',
            ];
        }

        // Pending leads
        $pendingLeads = Lead::where('brand_id', $brandId)
            ->where('status', 'pending')
            ->limit(10)
            ->get();

        foreach ($pendingLeads as $lead) {
            $opportunities[] = [
                'id' => $lead->id,
                'type' => 'leads_pending',
                'severity' => 'medium',
                'title' => "Lead: {$lead->name}",
                'description' => "Lead needs follow-up. Score: {$lead->score}",
                'source' => 'lead_monitor',
                'detectedAt' => $lead->created_at->toISOString(),
                'payload' => [
                    'lead_id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'score' => $lead->score,
                ],
                'impact' => 250,
                'requires_approval' => false,
            ];
        }

        // Analytics alerts
        $analytics = AnalyticsSnapshot::where('brand_id', $brandId)
            ->latest()
            ->first();

        if ($analytics && $analytics->conversions < 5) {
            $opportunities[] = [
                'id' => 'analytics_' . time(),
                'type' => 'analytics_alert',
                'severity' => 'high',
                'title' => 'Low Conversions Detected',
                'description' => "Conversions dropped below 5. Current: {$analytics->conversions}",
                'source' => 'analytics_monitor',
                'detectedAt' => now()->toISOString(),
                'payload' => [
                    'conversions' => $analytics->conversions,
                    'visitors' => $analytics->visitors,
                ],
                'impact' => 1000,
                'requires_approval' => true,
            ];
        }

        // Check if content gaps exist
        $contentGaps = $this->detectContentGaps($brandId);
        foreach ($contentGaps as $gap) {
            $opportunities[] = [
                'id' => 'content_gap_' . time(),
                'type' => 'content_generation',
                'severity' => 'medium',
                'title' => "Content Gap: {$gap['topic']}",
                'description' => $gap['reason'],
                'source' => 'content_monitor',
                'detectedAt' => now()->toISOString(),
                'payload' => [
                    'topic' => $gap['topic'],
                    'template' => 'blog',
                ],
                'impact' => 300,
                'requires_approval' => true,
            ];
        }

        return response()->json([
            'opportunities' => $opportunities,
            'total' => count($opportunities),
        ]);
    }

    // ============= ANALYTICS =============

    public function getAnalytics($brandId)
    {
        // Ensure fresh data (may trigger collection)
        $this->dataCollection->ensureFreshData($brandId);

        $analytics = AnalyticsSnapshot::where('brand_id', $brandId)
            ->latest()
            ->first();

        if (!$analytics) {
            return response()->json([
                'visitors' => 0,
                'pageViews' => 0,
                'sessions' => 0,
                'conversions' => 0,
                'revenue' => 0,
                'blogPostsThisMonth' => 0,
                'topPages' => [],
            ]);
        }

        return response()->json([
            'visitors' => $analytics->visitors ?? 0,
            'pageViews' => $analytics->page_views ?? 0,
            'sessions' => $analytics->sessions ?? 0,
            'conversions' => $analytics->conversions ?? 0,
            'revenue' => $analytics->revenue ?? 0,
            'blogPostsThisMonth' => $analytics->blog_posts_this_month ?? 0,
            'topPages' => $analytics->top_pages ?? [],
        ]);
    }

    // ============= SEO =============

    public function getSeoIssues($brandId)
    {
        $issues = SeoIssue::where('brand_id', $brandId)
            ->where('status', 'open')
            ->get();

        return response()->json([
            'issues' => $issues->map(function ($issue) {
                return [
                    'type' => $issue->type,
                    'description' => $issue->description,
                    'severity' => $issue->severity ?? 'medium',
                    'page' => $issue->page,
                ];
            }),
            'score' => $this->calculateSeoScore($issues),
        ]);
    }

    public function getSeoIssueById($brandId, $issueId)
    {
        $issue = SeoIssue::where('brand_id', $brandId)
            ->where('id', $issueId)
            ->firstOrFail();

        return response()->json($issue);
    }

    public function analyzeSeoIssue($brandId, $issueId)
    {
        $issue = SeoIssue::where('brand_id', $brandId)
            ->where('id', $issueId)
            ->firstOrFail();

        // Use the SEO assistant to analyze
        $analysis = $this->seoAssistant->analyzeIssue($brandId, $issue);

        return response()->json([
            'issue_id' => $issueId,
            'analysis' => $analysis['analysis'] ?? 'Detailed analysis of the SEO issue.',
            'recommendations' => $analysis['recommendations'] ?? ['Fix the issue manually'],
            'similar_issues' => $analysis['similar'] ?? [],
        ]);
    }

    public function getSeoRecommendations($brandId, $issueId)
    {
        $issue = SeoIssue::where('brand_id', $brandId)
            ->where('id', $issueId)
            ->firstOrFail();

        $recommendations = $this->seoAssistant->getRecommendations($brandId, $issue);

        return response()->json($recommendations);
    }

    public function getKeywordRankings($brandId, Request $request)
    {
        $pageUrl = $request->input('url');

        $rankings = \App\Models\KeywordRanking::where('brand_id', $brandId)
            ->when($pageUrl, function ($query) use ($pageUrl) {
                return $query->where('page_url', $pageUrl);
            })
            ->orderBy('position')
            ->limit(50)
            ->get();

        return response()->json($rankings);
    }

    // ============= LEADS =============

    public function getPendingLeads($brandId)
    {
        $leads = Lead::where('brand_id', $brandId)
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'leads' => $leads,
            'total' => $leads->count(),
        ]);
    }

    public function getLead($brandId, $leadId)
    {
        $lead = Lead::with('interactions')
            ->where('brand_id', $brandId)
            ->findOrFail($leadId);

        return response()->json($lead);
    }

    public function getLeadEngagement($brandId, $leadId)
    {
        $lead = Lead::where('brand_id', $brandId)
            ->findOrFail($leadId);

        $engagements = $lead->interactions()->get();

        return response()->json([
            'activities' => $engagements->count(),
            'emailsOpened' => $engagements->where('type', 'email_open')->count(),
            'last_activity' => $engagements->max('created_at'),
            'types' => $engagements->groupBy('type')->map->count(),
        ]);
    }

    public function getLeadContext($brandId, $leadId)
    {
        $lead = Lead::where('brand_id', $brandId)
            ->findOrFail($leadId);

        return response()->json([
            'notes' => $lead->notes ?? 'No notes available',
            'history' => $lead->interactions()->limit(10)->get(),
            'status' => $lead->status,
            'score' => $lead->score,
        ]);
    }

    public function generateFollowUpMessage($brandId, Request $request)
    {
        $leadId = $request->input('lead.id') ?? $request->input('lead_id');
        $lead = Lead::where('brand_id', $brandId)->findOrFail($leadId);

        $message = $this->leadService->generateFollowUp($lead);

        return response()->json([
            'message' => $message,
            'lead' => $lead,
        ]);
    }

    // ============= CAMPAIGNS =============

    public function getCampaigns($brandId)
    {
        $campaigns = Campaign::where('brand_id', $brandId)->get();

        return response()->json($campaigns);
    }

    public function pauseCampaign(Request $request)
    {
        $campaignId = $request->input('campaignId');
        $brandId = $request->input('brandId');

        $campaign = Campaign::where('brand_id', $brandId)
            ->where('id', $campaignId)
            ->firstOrFail();

        $campaign->status = 'paused';
        $campaign->save();

        return response()->json([
            'success' => true,
            'campaign' => $campaign,
        ]);
    }

    // ============= CONTENT =============

    public function analyzeContentGap($brandId, Request $request)
    {
        $topic = $request->input('topic');

        // Use content generator service
        $analysis = $this->contentGenerator->analyzeGap($brandId, $topic);

        return response()->json($analysis);
    }

    public function generateContentOutline(Request $request)
    {
        $topic = $request->input('topic');
        $template = $request->input('template', 'blog');

        $outline = $this->contentGenerator->generateOutline($topic, $template);

        return response()->json([
            'outline' => $outline,
            'template' => $template,
            'topic' => $topic,
        ]);
    }

    public function triggerContentGeneration(Request $request)
    {
        try {
            $brandId = $request->input('brandId');
            $topic = $request->input('topic');
            $template = $request->input('template', 'blog');

            // Validate brand exists
            $brand = \App\Models\Brand::findOrFail($brandId);

            // Map template to valid category ENUM
            $categoryMap = [
                'blog' => 'content',
                'social' => 'social',
                'email' => 'email',
                'web_copy' => 'web_copy',
            ];
            $category = $categoryMap[$template] ?? 'content';

            // ✅ 1. Create the AiAction first
            $action = \App\Models\AiAction::create([
                'brand_id' => $brandId,
                'title' => 'Generate content: ' . substr($topic, 0, 100),
                'description' => "Queued by agent for topic: {$topic}",
                'category' => $category,
                'target_platform' => $template,
                'target_keyword' => $topic,
                'status' => 'approved',
                'estimated_impact' => 500,
                'priority' => 3,
            ]);

            // ✅ 2. Dispatch the job with the AiAction object (not the int)
            \App\Jobs\GenerateContentForActionJob::dispatch($action);

            return response()->json([
                'success' => true,
                'action_id' => $action->id,
                'message' => 'Content generation queued',
                'status' => 'queued',
            ], 202);
        } catch (\Exception $e) {
            Log::error('Content generation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // ============= EXECUTION =============

    public function scan($brandId)
    {
        // Trigger a full scan
        // Dispatch job or run sync
        return response()->json([
            'success' => true,
            'message' => 'Scan initiated',
        ]);
    }

    public function executeAction(Request $request)
    {
        $brandId = $request->input('brandId');
        $action  = $request->input('action', []);
        $reason  = $request->input('reason', 'Queued by agent');

        // Extract the action name (supports both flat and nested shapes)
        $actionName = $action['name'] ?? ($action['action']['name'] ?? 'unknown');

        // Skip no-op actions entirely
        if ($actionName === 'no_action_needed' || $actionName === 'unknown') {
            return response()->json([
                'success' => true,
                'skipped' => true,
                'message' => 'No action was needed.',
            ], 200);
        }

        // Map raw action names to human-friendly titles + categories
        $actionMeta = [
            'trigger_content_generation' => ['title' => 'Generate content',       'category' => 'content'],
            'create_blog_post'           => ['title' => 'Create blog post',        'category' => 'content'],
            'generate_content'           => ['title' => 'Generate content',        'category' => 'content'],
            'resolve_seo_issue'          => ['title' => 'Fix SEO issue',           'category' => 'seo'],
            'run_site_scan'              => ['title' => 'Run site scan',           'category' => 'seo'],
            'notify_lead_response'       => ['title' => 'Follow up with lead',     'category' => 'strategy'],
            'pause_campaign'             => ['title' => 'Pause campaign',          'category' => 'strategy'],
            'adjust_campaign'            => ['title' => 'Adjust campaign',         'category' => 'strategy'],
        ];

        $meta  = $actionMeta[$actionName] ?? ['title' => ucwords(str_replace('_', ' ', $actionName)), 'category' => 'strategy'];
        $title = $meta['title'];

        // Add context to the title if topic/campaign name is present
        $payload = $action['payload'] ?? $action;
        if (!empty($payload['topic'])) {
            $title .= ': ' . substr($payload['topic'], 0, 80);
        } elseif (!empty($payload['campaignId'])) {
            $title .= ' (Campaign #' . $payload['campaignId'] . ')';
        } elseif (!empty($payload['lead_id'])) {
            $title .= ' (Lead #' . $payload['lead_id'] . ')';
        }

        // Create the AI action with proper metadata
        $aiAction = \App\Models\AiAction::create([
            'brand_id'          => $brandId,
            'title'             => $title,
            'description'       => $reason,
            'category'          => $meta['category'],
            'suggested_content' => json_encode($payload, JSON_PRETTY_PRINT),
            'target_url'        => $payload['target_url'] ?? null,
            'target_keyword'    => $payload['topic'] ?? null,
            'estimated_impact'  => $payload['estimated_impact'] ?? 100,
            'priority'          => 3,
            'status'            => 'pending',
        ]);

        Log::info('Agent action queued', [
            'action_id'   => $aiAction->id,
            'action_name' => $actionName,
            'brand_id'    => $brandId,
        ]);

        return response()->json([
            'success'   => true,
            'action_id' => $aiAction->id,
            'message'   => 'Action queued for review.',
        ], 201);
    }

    // ============= VERIFICATION =============

    public function startVerification(Request $request, $brandId)
    {
        $data = $request->validate([
            'action_name' => 'required|string',
            'opportunity_type' => 'nullable|string',
            'experience_id' => 'nullable|exists:agent_experiences,id',
            'before_metrics' => 'nullable|array',
        ]);

        $verification = ActionVerification::create([
            'brand_id' => $brandId,
            'action_name' => $data['action_name'],
            'opportunity_type' => $data['opportunity_type'] ?? null,
            'experience_id' => $data['experience_id'] ?? null,
            'before_metrics' => $data['before_metrics'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'verification' => $verification,
            'message' => 'Verification started',
        ], 201);
    }

    public function getVerification($brandId, $verificationId)
    {
        $verification = ActionVerification::where('brand_id', $brandId)
            ->where('id', $verificationId)
            ->firstOrFail();

        return response()->json($verification);
    }

    public function completeVerification(Request $request, $brandId, $verificationId)
    {
        $verification = ActionVerification::where('brand_id', $brandId)
            ->where('id', $verificationId)
            ->firstOrFail();

        $validated = $request->validate([
            'after_metrics' => 'required|array',
            'was_successful' => 'required|boolean',
            'improvement_percentage' => 'nullable|numeric',
            'verification_notes' => 'nullable|string',
        ]);

        $verification->update([
            'after_metrics' => $validated['after_metrics'],
            'was_successful' => $validated['was_successful'],
            'improvement_percentage' => $validated['improvement_percentage'] ?? null,
            'verification_notes' => $validated['verification_notes'] ?? null,
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        // Update experience if linked
        if ($verification->experience_id) {
            $experience = AgentExperience::find($verification->experience_id);
            if ($experience) {
                $experience->was_successful = $validated['was_successful'];
                $experience->improvement_percentage = $validated['improvement_percentage'] ?? null;
                $experience->outcome = array_merge($experience->outcome ?? [], [
                    'verified_at' => now()->toISOString(),
                    'verification_notes' => $validated['verification_notes'] ?? null,
                ]);
                $experience->save();
            }
        }

        return response()->json([
            'verification' => $verification,
            'message' => 'Verification completed',
        ]);
    }

    // ============= LEARNING =============

    public function recordLearning(Request $request, $brandId)
    {
        $validated = $request->validate([
            'action_name' => 'required|string',
            'opportunity_type' => 'nullable|string',
            'severity' => 'nullable|string',
            'context' => 'nullable|array',
            'decision' => 'nullable|array',
            'outcome' => 'nullable|array',
            'confidence' => 'nullable|numeric|min:0|max:1',
            'was_autonomous' => 'boolean',
            'was_successful' => 'boolean',
            'improvement_percentage' => 'nullable|numeric',
            'duration_seconds' => 'nullable|integer',
            'human_feedback' => 'nullable|string',
        ]);

        $experience = AgentExperience::create([
            'brand_id' => $brandId,
            'action_name' => $validated['action_name'],
            'opportunity_type' => $validated['opportunity_type'] ?? 'unknown',
            'severity' => $validated['severity'] ?? 'medium',
            'context' => $validated['context'] ?? null,
            'decision' => $validated['decision'] ?? null,
            'outcome' => $validated['outcome'] ?? null,
            'confidence' => $validated['confidence'] ?? null,
            'was_autonomous' => $validated['was_autonomous'] ?? false,
            'was_successful' => $validated['was_successful'] ?? true,
            'improvement_percentage' => $validated['improvement_percentage'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'human_feedback' => $validated['human_feedback'] ?? null,
            'status' => 'recorded',
        ]);

        return response()->json([
            'experience' => $experience,
            'message' => 'Learning recorded successfully',
        ], 201);
    }

    // public function getSimilarExperiences(Request $request, $brandId)
    // {
    //     $type = $request->input('type');
    //     $severity = $request->input('severity');
    //     $limit = $request->input('limit', 20);
    //
    //     $query = AgentExperience::where('brand_id', $brandId);
    //
    //     if ($type) {
    //         $query->where('opportunity_type', $type);
    //     }
    //
    //     if ($severity) {
    //         $query->where('severity', $severity);
    //     }
    //
    //     $experiences = $query->orderBy('created_at', 'desc')
    //         ->limit($limit)
    //         ->get();
    //
    //     $total = $experiences->count();
    //     $successful = $experiences->where('was_successful', true)->count();
    //     $successRate = $total > 0 ? ($successful / $total) * 100 : 0;
    //     $avgImprovement = $experiences->where('was_successful', true)
    //         ->avg('improvement_percentage') ?? 0;
    //
    //     return response()->json([
    //         'experiences' => $experiences,
    //         'stats' => [
    //             'total' => $total,
    //             'successful' => $successful,
    //             'success_rate' => round($successRate, 2),
    //             'avg_improvement' => round($avgImprovement, 2),
    //             'latest' => $experiences->first(),
    //         ],
    //     ]);
    // }

    // ============= HEALTH & UTILITY =============

    public function ping()
    {
        return response()->json([
            'success' => true,
            'timestamp' => now()->toISOString(),
            'service' => 'Vumbi AI Agent API',
            'version' => '1.0.0',
        ]);
    }

    public function pingAI()
    {
        // Check if AI Gateway is available
        $available = $this->aiGateway->isAvailable();

        return response()->json([
            'success' => $available,
            'message' => $available ? 'AI service available' : 'AI service unavailable',
            'timestamp' => now()->toISOString(),
        ]);
    }

    // ============= PRIVATE HELPERS =============

    private function calculateSeoScore($issues)
    {
        $total = $issues->count();
        if ($total === 0) {
            return 100;
        }

        $critical = $issues->where('severity', 'high')->count();
        $score = 100 - ($critical * 10) - ($total * 2);

        return max(0, min(100, $score));
    }

    private function detectContentGaps($brandId)
    {
        // Simple detection: look for topics with high search volume but no content
        // This would ideally use Ahrefs data
        $gaps = [];

        // Example: check if there are travel guides for popular destinations
        $destinations = ['Maasai Mara', 'Nairobi', 'Diani', 'Amboseli', 'Samburu'];
        foreach ($destinations as $destination) {
            $hasContent = \App\Models\BlogPost::where('brand_id', $brandId)
                ->where('title', 'LIKE', "%{$destination}%")
                ->exists();

            if (!$hasContent) {
                $gaps[] = [
                    'topic' => "Complete Guide to {$destination}",
                    'reason' => "No content exists for {$destination}",
                ];
            }
        }

        return $gaps;
    }

    public function rollbackAction(Request $request, $brandId)
    {
        $actionId = $request->input('action_id');
        $actionName = $request->input('action_name');
        // Logic to rollback
        return response()->json(['success' => true, 'message' => "Rollback initiated for $actionName"]);
    }

    public function getSimilarExperiences(Request $request, $brandId)
    {
        $type = $request->input('type');
        $severity = $request->input('severity');

        // If type or severity missing, return empty (avoid SQL errors)
        if (!$type || !$severity) {
            return response()->json(['experiences' => [], 'stats' => []]);
        }

        try {
            $experiences = AgentExperience::where('brand_id', $brandId)
                ->where('opportunity_type', $type)
                ->where('severity', $severity)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $total = $experiences->count();
            $successful = $experiences->where('was_successful', true)->count();
            $successRate = $total > 0 ? ($successful / $total) * 100 : 0;
            $avgImprovement = $experiences->where('was_successful', true)->avg('improvement_percentage') ?? 0;

            return response()->json([
                'experiences' => $experiences,
                'stats' => [
                    'total' => $total,
                    'successful' => $successful,
                    'success_rate' => round($successRate, 2),
                    'avg_improvement' => round($avgImprovement, 2),
                    'latest' => $experiences->first(),
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error but return a friendly response
            Log::error('Error fetching similar experiences: ' . $e->getMessage());
            return response()->json(['experiences' => [], 'stats' => []], 200);
        }
    }

    /**
     * Analyze analytics data and provide insights for the agent.
     */
    public function analyzeAnalytics($brandId)
    {
        $brand = Brand::findOrFail($brandId);

        // Fetch the latest analytics snapshot
        $analytics = AnalyticsSnapshot::where('brand_id', $brandId)
            ->latest()
            ->first();

        if (!$analytics) {
            return response()->json([
                'success' => false,
                'message' => 'No analytics data found for this brand.',
            ], 404);
        }

        $visitors = $analytics->visitors ?? 0;
        $conversions = $analytics->conversions ?? 0;
        $revenue = $analytics->revenue ?? 0;
        $conversionRate = $visitors > 0 ? ($conversions / $visitors) * 100 : 0;

        // Simple analysis logic (can be expanded)
        $issues = [];
        $recommendations = [];

        if ($conversionRate < 2) {
            $issues[] = 'Conversion rate is below 2%.';
            $recommendations[] = 'Run an A/B test on the main landing page.';
            $recommendations[] = 'Improve call-to-action placement.';
        }

        if ($visitors < 100) {
            $issues[] = 'Traffic is low.';
            $recommendations[] = 'Increase marketing efforts (SEO, PPC, social).';
        }

        if ($revenue < 500) {
            $issues[] = 'Revenue is below $500.';
            $recommendations[] = 'Consider upselling or cross-selling strategies.';
        }

        // Additional insights from historical data
        $previous = AnalyticsSnapshot::where('brand_id', $brandId)
            ->where('id', '<', $analytics->id)
            ->latest()
            ->first();

        $trend = 'stable';
        if ($previous) {
            $prevConversions = $previous->conversions ?? 0;
            if ($conversions > $prevConversions) {
                $trend = 'up';
            } elseif ($conversions < $prevConversions) {
                $trend = 'down';
            }
        }

        return response()->json([
            'success' => true,
            'brand_id' => $brandId,
            'analytics' => [
                'visitors' => $visitors,
                'conversions' => $conversions,
                'revenue' => $revenue,
                'conversion_rate' => round($conversionRate, 2),
                'trend' => $trend,
            ],
            'issues' => $issues,
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * Check which fingerprints are new (not yet processed today).
     * Returns new, recurring, and already-processed-today lists.
     */
    public function checkOpportunities(Request $request)
    {
        $validated = $request->validate([
            'brand_id'                              => 'required|integer|exists:brands,id',
            'opportunities'                         => 'required|array',
            'opportunities.*.fingerprint'           => 'required|string|size:64',
            'opportunities.*.stable_key'            => 'required|string|size:64',
            'opportunities.*.type'                  => 'required|string',
        ]);

        $brandId = $validated['brand_id'];
        $opps    = $validated['opportunities'];

        $fingerprints = array_column($opps, 'fingerprint');

        // Which were already processed or are being processed today?
        $processedToday = AgentOpportunityTracking::forBrand($brandId)
            ->whereIn('fingerprint', $fingerprints)
            ->whereDate('tracked_date', today())
            ->whereIn('status', ['processed', 'processing'])
            ->pluck('fingerprint')
            ->toArray();

        $processedSet = array_flip($processedToday);

        $new       = [];
        $recurring = [];

        foreach ($opps as $opp) {
            $fp = $opp['fingerprint'];
            if (isset($processedSet[$fp])) {
                continue; // Already processed today
            }

            $sk = $opp['stable_key'];

            // Has this stable_key been seen on any prior day?
            $prior = AgentOpportunityTracking::forBrand($brandId)
                ->forStableKey($sk)
                ->whereDate('tracked_date', '<', today())
                ->orderByDesc('tracked_date')
                ->first();

            if ($prior) {
                $recurring[] = [
                    'fingerprint'            => $fp,
                    'stable_key'             => $sk,
                    'recurrence_count'       => ($prior->recurrence_count ?? 1) + 1,
                    'first_seen_at'          => optional($prior->first_seen_at)->toISOString(),
                    'last_attempt_status'    => $prior->status,
                    'last_attempt_action_id' => $prior->action_id,
                ];
            } else {
                $new[] = [
                    'fingerprint' => $fp,
                    'stable_key'  => $sk,
                ];
            }
        }

        return response()->json([
            'new'                     => $new,
            'recurring'               => $recurring,
            'already_processed_today' => $processedToday,
        ]);
    }

    /**
     * Mark an opportunity as processing / processed / failed.
     */
    public function markOpportunity(Request $request)
    {
        $validated = $request->validate([
            'brand_id'         => 'required|integer|exists:brands,id',
            'fingerprint'      => 'required|string|size:64',
            'stable_key'       => 'required|string|size:64',
            'opportunity_type' => 'required|string',
            'status'           => 'required|in:processing,processed,failed,escalated',
            'opportunity_data' => 'nullable|array',
            'action_id'        => 'nullable|integer',
        ]);

        $brandId = $validated['brand_id'];
        $fp      = $validated['fingerprint'];
        $sk      = $validated['stable_key'];

        // How many times has this stable_key been seen before today?
        $priorCount = AgentOpportunityTracking::forBrand($brandId)
            ->forStableKey($sk)
            ->where('fingerprint', '!=', $fp)
            ->count();

        $tracking = AgentOpportunityTracking::firstOrNew([
            'fingerprint' => $fp,
        ]);

        $tracking->fill([
            'brand_id'         => $brandId,
            'stable_key'       => $sk,
            'tracked_date'     => today(),
            'opportunity_type' => $validated['opportunity_type'],
            'opportunity_data' => $validated['opportunity_data'] ?? null,
            'status'           => $validated['status'],
            'action_id'        => $validated['action_id'] ?? null,
            'last_processed_at' => now(),
            'recurrence_count' => $priorCount + 1,
        ]);

        if (!$tracking->exists) {
            $tracking->first_seen_at = now();
        }

        $tracking->last_seen_at = now();
        $tracking->times_seen   = ($tracking->times_seen ?? 0) + 1;

        $tracking->save();

        return response()->json([
            'success'     => true,
            'tracking_id' => $tracking->id,
            'status'      => $tracking->status,
        ]);
    }

    /**
     * Get outcomes the agent hasn't been notified about yet.
     * Includes approvals awaiting execution, rejections, and revisions.
     */
    public function getPendingOutcomes($brandId)
    {
        $brand = Brand::findOrFail($brandId);

        // Actions the agent hasn't been told about
        $outcomes = AiAction::where('brand_id', $brandId)
            ->whereNull('agent_notified_at')
            ->whereIn('status', ['approved', 'rejected', 'revision'])
            ->orderBy('updated_at', 'asc')
            ->get();

        return response()->json([
            'brand_id' => $brandId,
            'outcomes' => $outcomes->map(function ($action) {
                $actionData = null;
                if ($action->suggested_content) {
                    $decoded = json_decode($action->suggested_content, true);
                    $actionData = $decoded ?: ['raw' => $action->suggested_content];
                }

                return [
                    'action_id'                => $action->id,
                    'status'                   => $action->status,
                    'title'                    => $action->title,
                    'category'                 => $action->category,
                    'approved_at'              => optional($action->approved_at)->toISOString(),
                    'rejected_at'              => optional($action->rejected_at)->toISOString(),
                    'reviewed_at'              => optional($action->reviewed_at)->toISOString(),
                    'rejection_reason'         => $action->rejection_reason,
                    'review_notes'             => $action->review_notes,
                    'retry_status'             => $action->retry_status ?? 'none',
                    'expected_retry_approach'  => $action->expected_retry_approach,
                    'opportunity_fingerprint'  => $action->opportunity_fingerprint,
                    'opportunity_stable_key'   => $action->opportunity_stable_key,
                    'origin'                   => $action->origin ?? 'original',
                    'action_data'              => $actionData,
                ];
            }),
            'count' => $outcomes->count(),
        ]);
    }

    /**
     * Acknowledge that the agent has handled these outcomes.
     */
    public function acknowledgeOutcomes(Request $request)
    {
        $validated = $request->validate([
            'brand_id'   => 'required|integer|exists:brands,id',
            'action_ids' => 'required|array',
            'action_ids.*' => 'required|integer|exists:ai_actions,id',
        ]);

        $count = AiAction::where('brand_id', $validated['brand_id'])
            ->whereIn('id', $validated['action_ids'])
            ->update(['agent_notified_at' => now()]);

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    /**
     * Authorize a retry for a rejected action, with optional guidance.
     */
    public function authorizeRetry(Request $request, $actionId)
    {
        $validated = $request->validate([
            'expected_retry_approach' => 'nullable|string|max:2000',
            'hold'                    => 'nullable|boolean',
        ]);

        $action = AiAction::findOrFail($actionId);

        $action->update([
            'retry_status'            => ($validated['hold'] ?? false) ? 'held' : 'authorized',
            'expected_retry_approach' => $validated['expected_retry_approach'] ?? null,
            // Clear agent_notified_at so the agent re-processes this on next cycle
            'agent_notified_at'       => null,
        ]);

        return response()->json([
            'success'      => true,
            'action_id'    => $action->id,
            'retry_status' => $action->retry_status,
        ]);
    }

    /**
     * Get full history of a recurring opportunity by stable_key.
     */
    public function getOpportunityHistory($brandId, $stableKey)
    {
        $trackings = AgentOpportunityTracking::where('brand_id', $brandId)
            ->where('stable_key', $stableKey)
            ->orderBy('tracked_date', 'asc')
            ->get();

        if ($trackings->isEmpty()) {
            return response()->json([
                'brand_id'    => $brandId,
                'stable_key'  => $stableKey,
                'attempts'    => [],
                'summary'     => [
                    'total_attempts'    => 0,
                    'successful'        => 0,
                    'failed'            => 0,
                    'first_seen'        => null,
                    'last_seen'         => null,
                ],
            ]);
        }

        $attempts = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($trackings as $index => $tracking) {
            $action = $tracking->action_id
                ? AiAction::find($tracking->action_id)
                : null;

            $attempt = [
                'attempt_number'  => $index + 1,
                'date'            => optional($tracking->tracked_date)->toDateString(),
                'status'          => $tracking->status,
                'action_id'       => $tracking->action_id,
                'action_name'     => $action?->title,
                'action_category' => $action?->category,
                'rejection_reason' => $action?->rejection_reason,
                'review_notes'    => $action?->review_notes,
                'retry_status'    => $action?->retry_status,
                'expected_approach' => $action?->expected_retry_approach,
            ];

            if (in_array($tracking->status, ['processed'])) {
                $successCount++;
            } elseif (in_array($tracking->status, ['failed', 'escalated'])) {
                $failCount++;
            }

            $attempts[] = $attempt;
        }

        // Gather human rejection reasons across all attempts
        $rejectionReasons = collect($attempts)
            ->pluck('rejection_reason')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'brand_id'   => $brandId,
            'stable_key' => $stableKey,
            'attempts'   => $attempts,
            'summary'    => [
                'total_attempts'    => count($attempts),
                'successful'        => $successCount,
                'failed'            => $failCount,
                'first_seen'        => optional($trackings->first()->tracked_date)->toDateString(),
                'last_seen'         => optional($trackings->last()->tracked_date)->toDateString(),
                'rejection_reasons' => $rejectionReasons,
            ],
        ]);
    }

    /**
     * Human responds to an escalation.
     */
    public function respondToEscalation(Request $request, $actionId)
    {
        $validated = $request->validate([
            'response'       => 'required|in:investigate,retry,resolve,snooze',
            'notes'          => 'nullable|string|max:2000',
            'snooze_days'    => 'nullable|integer|min:1|max:30',
        ]);

        $action = AiAction::findOrFail($actionId);

        if (!$action->isEscalation()) {
            return response()->json([
                'success' => false,
                'error'   => 'This action is not an escalation.',
            ], 400);
        }

        $snoozeUntil = null;
        if ($validated['response'] === 'snooze') {
            $days = $validated['snooze_days'] ?? 7;
            $snoozeUntil = now()->addDays($days)->toDateString();
        }

        $action->update([
            'human_response'       => $validated['response'],
            'human_response_notes' => $validated['notes'] ?? null,
            'human_response_at'    => now(),
            'snooze_until'         => $snoozeUntil,
            // Clear agent_notified_at so the agent re-processes this
            'agent_notified_at'    => null,
        ]);

        \Log::info('Escalation response recorded', [
            'action_id' => $action->id,
            'response'  => $validated['response'],
            'snooze_until' => $snoozeUntil,
        ]);

        return response()->json([
            'success'      => true,
            'action_id'    => $action->id,
            'response'     => $validated['response'],
            'snooze_until' => $snoozeUntil,
        ]);
    }

    /**
     * Get escalations awaiting human response.
     */
    public function getPendingEscalations($brandId)
    {
        $escalations = AiAction::where('brand_id', $brandId)
            ->where('category', 'escalation')
            ->whereNull('human_response')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'escalations' => $escalations->map(function ($action) {
                $payload = json_decode($action->suggested_content, true) ?? [];
                return [
                    'action_id'        => $action->id,
                    'title'            => $action->title,
                    'description'      => $action->description,
                    'priority'         => $action->priority,
                    'stable_key'       => $payload['stable_key'] ?? null,
                    'recurrence_count' => $payload['recurrence_count'] ?? null,
                    'first_seen'       => $payload['first_seen'] ?? null,
                    'prior_attempts'   => $payload['prior_attempts'] ?? [],
                    'created_at'       => optional($action->created_at)->toISOString(),
                ];
            }),
            'count' => $escalations->count(),
        ]);
    }

    /**
     * Get calibration data for a brand + opportunity type.
     * Used by the agent before scoring confidence.
     */
    public function getCalibration(Request $request, $brandId)
    {
        $opportunityType = $request->query('type');
        $actionName      = $request->query('action');

        $query = ConfidenceCalibration::forBrand($brandId);

        if ($opportunityType) {
            $query->where('opportunity_type', $opportunityType);
        }
        if ($actionName) {
            $query->where('action_name', $actionName);
        }

        $buckets = $query->orderBy('confidence_bucket')->get()->map(function ($cal) {
            return [
                'confidence_bucket'    => $cal->confidence_bucket,
                'range'                => sprintf('%.1f–%.1f', $cal->confidence_bucket / 10, ($cal->confidence_bucket + 1) / 10),
                'opportunity_type'     => $cal->opportunity_type,
                'action_name'          => $cal->action_name,
                'total_predictions'    => $cal->total_predictions,
                'successful_predictions' => $cal->successful_predictions,
                'actual_accuracy'      => $cal->actual_accuracy,
                'sample_sufficient'    => $cal->total_predictions >= 10,
            ];
        });

        return response()->json([
            'brand_id'  => $brandId,
            'buckets'   => $buckets,
            'has_data'  => $buckets->isNotEmpty(),
        ]);
    }

    /**
     * Record an observed outcome against a confidence prediction.
     * Called by the agent after every verified execution.
     */
    public function recordCalibration(Request $request)
    {
        $validated = $request->validate([
            'brand_id'          => 'required|integer|exists:brands,id',
            'stated_confidence' => 'required|numeric|min:0|max:1',
            'opportunity_type'  => 'required|string',
            'action_name'       => 'nullable|string',
            'was_successful'    => 'required|boolean',
        ]);

        $bucket = ConfidenceCalibration::bucket($validated['stated_confidence']);

        $cal = ConfidenceCalibration::firstOrNew([
            'brand_id'         => $validated['brand_id'],
            'confidence_bucket' => $bucket,
            'opportunity_type' => $validated['opportunity_type'],
            'action_name'      => $validated['action_name'],
        ]);

        $cal->total_predictions++;
        if ($validated['was_successful']) {
            $cal->successful_predictions++;
        }

        $cal->actual_accuracy = $cal->total_predictions > 0
            ? round($cal->successful_predictions / $cal->total_predictions, 4)
            : 0.0;

        $cal->last_updated_at = now();
        $cal->save();

        return response()->json([
            'success'          => true,
            'bucket'           => $bucket,
            'total_predictions' => $cal->total_predictions,
            'actual_accuracy'  => $cal->actual_accuracy,
        ]);
    }

    /**
     * Calibration summary for the dashboard.
     */
    public function getCalibrationSummary($brandId)
    {
        $rows = ConfidenceCalibration::forBrand($brandId)
            ->where('total_predictions', '>=', 5)
            ->orderBy('opportunity_type')
            ->orderBy('confidence_bucket')
            ->get();

        // Group by opportunity type
        $grouped = $rows->groupBy('opportunity_type')->map(function ($group) {
            return $group->map(function ($cal) {
                return [
                    'bucket'    => $cal->confidence_bucket,
                    'range'     => sprintf('%.1f–%.1f', $cal->confidence_bucket / 10, ($cal->confidence_bucket + 1) / 10),
                    'samples'   => $cal->total_predictions,
                    'accuracy'  => $cal->actual_accuracy,
                    'drift'     => round($cal->actual_accuracy - ($cal->confidence_bucket / 10), 3),
                ];
            })->values();
        });

        return response()->json([
            'brand_id' => $brandId,
            'by_type'  => $grouped,
        ]);
    }
}
