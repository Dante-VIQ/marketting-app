<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateContentForActionJob;
use App\Jobs\ScanPageJob;
use App\Models\ActionVerification;
use App\Models\AgentExperience;
use App\Models\AgentOpportunityTracking;
use App\Models\AiAction;
use App\Models\AiBrief;
use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\ConfidenceCalibration;
use App\Models\Lead;
use App\Models\SeoIssue;
use App\Models\TourPackage;
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
                'target_url' => $issue->page_url,
                'payload' => [
                    'page' => $issue->page_url,
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
                'target_url' => null,
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
                'target_url' => null,
                'payload' => [
                    'conversions' => $analytics->conversions,
                    'visitors' => $analytics->visitors,
                ],
                'impact' => 1000,
                'requires_approval' => true,
            ];
        }

        // Content gaps — driven by real tour inventory, not a hardcoded list
        $contentGaps = $this->detectContentGaps($brandId);
        foreach ($contentGaps as $gap) {
            $opportunities[] = [
                'id' => 'content_gap_' . ($gap['tour_id'] ?? time()),
                'type' => 'content_generation',
                'severity' => 'medium',
                'title' => "Content Gap: {$gap['topic']}",
                'description' => $gap['reason'],
                'source' => 'content_monitor',
                'detectedAt' => now()->toISOString(),
                'target_url' => null,
                'payload' => [
                    'topic' => $gap['topic'],
                    'template' => 'blog',
                    'tour_id' => $gap['tour_id'] ?? null,
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
                    'id' => $issue->id,
                    'type' => $issue->type,
                    'description' => $issue->description,
                    'severity' => $issue->severity ?? 'medium',
                    'page' => $issue->page_url,
                    'status' => $issue->status,
                    'created_at' => $issue->created_at->toISOString(),
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
        $brandId    = $request->input('brandId');
        $reason     = $request->input('reason', 'Agent recommendation');

        $campaign = Campaign::where('brand_id', $brandId)
            ->where('id', $campaignId)
            ->firstOrFail();

        // No real ad-platform integration exists yet — queue for human review.
        $action = AiAction::create([
            'brand_id'          => $brandId,
            'title'             => "Pause campaign: {$campaign->name}",
            'category'          => 'campaign',
            'description'       => $reason,
            'target_keyword'    => null,
            'estimated_impact'  => 500,
            'priority'          => 4,
            'status'            => 'pending',
            'origin'            => 'agent',
        ]);

        Log::info('Campaign pause queued for human review', [
            'action_id'   => $action->id,
            'campaign_id' => $campaignId,
            'brand_id'    => $brandId,
        ]);

        return response()->json([
            'success'   => true,
            'action_id' => $action->id,
            'queued'    => true,
            'message'   => 'Campaign pause queued for human approval',
        ]);
    }

    // ============= CONTENT =============

    public function analyzeContentGap($brandId, Request $request)
    {
        $topic = $request->input('topic');

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

            $brand = \App\Models\Brand::findOrFail($brandId);

            $categoryMap = [
                'blog' => 'content',
                'social' => 'social',
                'email' => 'email',
                'web_copy' => 'web_copy',
            ];
            $category = $categoryMap[$template] ?? 'content';

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
                'origin' => 'agent',
            ]);

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
        $brand = Brand::findOrFail($brandId);

        $issues = SeoIssue::where('brand_id', $brandId)
            ->where('status', 'open')
            ->whereIn('severity', ['high', 'critical'])
            ->limit(20)
            ->get();

        $actionIds = [];
        foreach ($issues as $issue) {
            if (!$issue->page_url) {
                continue;
            }

            $action = AiAction::create([
                'brand_id'         => $brandId,
                'title'            => "Fix SEO: {$issue->type}",
                'category'         => 'seo',
                'description'      => $issue->description,
                'target_url'       => $issue->page_url,
                'estimated_impact' => $issue->severity === 'critical' ? 1000 : 500,
                'priority'         => $issue->severity === 'critical' ? 5 : 3,
                'status'           => 'approved',
                'executed_at'      => now(),
                'origin'           => 'agent',
            ]);

            ScanPageJob::dispatch($brand, $issue->page_url, $action);
            $actionIds[] = $action->id;
        }

        \App\Models\GuardianAuditLog::create([
            'brand_id'    => $brandId,
            'user_id'     => null,
            'fingerprint' => 'agent_scan_' . now()->timestamp,
            'event_type'  => 'agent_scan_dispatched',
            'metadata'    => [
                'action_ids' => $actionIds,
                'issue_count' => count($issues),
            ],
        ]);

        Log::info('Agent scan queued', [
            'brand_id' => $brandId,
            'scans'    => count($actionIds),
        ]);

        return response()->json([
            'success'    => true,
            'action_id'  => $actionIds[0] ?? null,
            'action_ids' => $actionIds,
            'queued'     => count($actionIds),
            'message'    => 'Queued ' . count($actionIds) . ' SEO scans',
        ]);
    }

    public function executeAction(Request $request)
    {
        $brandId = $request->input('brandId');
        $action  = $request->input('action', []);
        $reason  = $request->input('reason', 'Queued by agent');

        $actionName = $action['name'] ?? ($action['action']['name'] ?? 'unknown');

        if ($actionName === 'no_action_needed' || $actionName === 'unknown') {
            return response()->json([
                'success' => true,
                'skipped' => true,
                'message' => 'No action was needed.',
            ], 200);
        }

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

        $payload = $action['payload'] ?? $action;
        if (!empty($payload['topic'])) {
            $title .= ': ' . substr($payload['topic'], 0, 80);
        } elseif (!empty($payload['campaignId'])) {
            $title .= ' (Campaign #' . $payload['campaignId'] . ')';
        } elseif (!empty($payload['lead_id'])) {
            $title .= ' (Lead #' . $payload['lead_id'] . ')';
        }

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
            'origin'            => 'agent',
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

    /**
     * Execute an approved action. Called by the Python agent after a human
     * approves a queued action. Routes by category to the right service.
     */
    public function executeApprovedAction($actionId)
    {
        $action = AiAction::findOrFail($actionId);

        if ($action->status !== 'approved') {
            return response()->json([
                'success' => false,
                'error'   => "Action status is '{$action->status}', not approved.",
            ], 400);
        }

        if ($action->executed_at) {
            return response()->json([
                'success'      => true,
                'already_done' => true,
                'action_id'    => $action->id,
                'executed_at'  => $action->executed_at->toISOString(),
            ]);
        }

        $brand = Brand::findOrFail($action->brand_id);
        $result = null;

        try {
            switch ($action->category) {
                case 'seo':
                    if ($action->target_url) {
                        \App\Jobs\ScanPageJob::dispatch($brand, $action->target_url, $action);
                        $result = ['dispatched' => 'ScanPageJob', 'target_url' => $action->target_url];
                    } else {
                        $result = ['skipped' => 'no target_url'];
                    }
                    break;

                case 'content':
                case 'social':
                case 'email':
                case 'web_copy':
                    \App\Jobs\GenerateContentForActionJob::dispatch($action);
                    $result = ['dispatched' => 'GenerateContentForActionJob'];
                    break;

                case 'campaign':
                    $result = ['noted' => 'campaign action requires manual execution'];
                    break;

                case 'strategy':
                default:
                    $result = ['noted' => 'no automated execution for this category'];
                    break;
            }

            $action->update([
                'executed_at' => now(),
                'origin'      => $action->origin ?? 'human',
            ]);

            Log::info('Approved action executed', [
                'action_id' => $action->id,
                'category'  => $action->category,
                'result'    => $result,
            ]);

            \App\Models\GuardianAuditLog::create([
                'brand_id'    => $action->brand_id,
                'user_id'     => null,
                'fingerprint' => 'agent_action_' . $action->id,
                'event_type'  => 'agent_action_executed',
                'metadata'    => [
                    'action_id'   => $action->id,
                    'category'    => $action->category,
                    'target_url'  => $action->target_url,
                    'result'      => $result,
                ],
            ]);

            return response()->json([
                'success'   => true,
                'action_id' => $action->id,
                'category'  => $action->category,
                'result'    => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to execute approved action', [
                'action_id' => $action->id,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
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

    /**
     * Detect real content gaps by checking whether each active tour
     * package has matching content. No hardcoded destination list.
     */
    private function detectContentGaps($brandId)
    {
        $gaps = [];

        $tours = TourPackage::forBrand((int) $brandId)->active()->get();

        foreach ($tours as $tour) {
            $topic = $tour->destination ?: $tour->name;
            if (!$topic) {
                continue;
            }

            $hasContent = \App\Models\BlogPost::where('brand_id', $brandId)
                ->where(function ($q) use ($topic) {
                    $q->where('title', 'LIKE', "%{$topic}%")
                      ->orWhere('content', 'LIKE', "%{$topic}%");
                })
                ->exists();

            if (!$hasContent) {
                $gaps[] = [
                    'topic' => "Complete Guide to {$topic}",
                    'reason' => "No content exists for tour: {$tour->name}",
                    'tour_id' => $tour->id,
                ];
            }
        }

        return $gaps;
    }

    public function getSimilarExperiences(Request $request, $brandId)
    {
        $type = $request->input('type');
        $severity = $request->input('severity');

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
            Log::error('Error fetching similar experiences: ' . $e->getMessage());
            return response()->json(['experiences' => [], 'stats' => []], 200);
        }
    }

    public function analyzeAnalytics($brandId)
    {
        $brand = Brand::findOrFail($brandId);

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
                continue;
            }

            $sk = $opp['stable_key'];

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

    public function markOpportunity(Request $request)
    {
        $validated = $request->validate([
            'brand_id'         => 'required|integer|exists:brands,id',
            'fingerprint'      => 'required|string|size:64',
            'stable_key'       => 'required|string|size:64',
            'opportunity_type' => 'required|string',
            'status'           => 'required|in:processing,processed,failed,escalated,resolved',
            'opportunity_data' => 'nullable|array',
            'action_id'        => 'nullable|integer',
        ]);

        $brandId = $validated['brand_id'];
        $fp      = $validated['fingerprint'];
        $sk      = $validated['stable_key'];

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
     * Includes a safety net for approved actions that were never executed.
     */
    public function getPendingOutcomes($brandId)
    {
        $brand = Brand::findOrFail($brandId);

        $outcomes = AiAction::where('brand_id', $brandId)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('agent_notified_at')
                       ->whereIn('status', ['approved', 'rejected', 'revision']);
                })
                ->orWhere(function ($q2) {
                    $q2->where('status', 'approved')
                       ->whereNull('executed_at');
                });
            })
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
                    'executed_at'              => optional($action->executed_at)->toISOString(),
                    'action_data'              => $actionData,
                ];
            }),
            'count' => $outcomes->count(),
        ]);
    }

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
            'agent_notified_at'       => null,
        ]);

        return response()->json([
            'success'      => true,
            'action_id'    => $action->id,
            'retry_status' => $action->retry_status,
        ]);
    }

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
            'agent_notified_at'    => null,
        ]);

        Log::info('Escalation response recorded', [
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

    public function getCalibrationSummary($brandId)
    {
        $rows = ConfidenceCalibration::forBrand($brandId)
            ->where('total_predictions', '>=', 5)
            ->orderBy('opportunity_type')
            ->orderBy('confidence_bucket')
            ->get();

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

    public function registerVerification(Request $request)
    {
        $validated = $request->validate([
            'brand_id'            => 'required|integer|exists:brands,id',
            'action_id'           => 'required|integer|exists:ai_actions,id',
            'action_name'         => 'required|string',
            'metrics_at_execution' => 'nullable|array',
        ]);

        $action = AiAction::findOrFail($validated['action_id']);
        $windows = config("verification.windows.{$validated['action_name']}", null);

        if (!$windows) {
            return response()->json([
                'success' => false,
                'error'   => "No verification config for action: {$validated['action_name']}",
            ], 400);
        }

        $now = now();

        $action->update([
            'metrics_at_execution'  => $validated['metrics_at_execution'] ?? null,
            'verification_status'   => 'pending',
            'verify_at_hour_1'      => $windows['hour_1'] ? $now->copy()->addSeconds($windows['hour_1']) : null,
            'verify_at_day_1'       => $windows['day_1'] ? $now->copy()->addSeconds($windows['day_1']) : null,
        ]);

        return response()->json([
            'success'         => true,
            'action_id'       => $action->id,
            'schedule'        => [
                'hour_1' => optional($action->verify_at_hour_1)->toISOString(),
                'day_1'  => optional($action->verify_at_day_1)->toISOString(),
            ],
        ]);
    }

    /**
     * Get actions that are due for a verification phase.
     * `action_name` is set to the category so the Python verifier
     * can route metric fetching correctly.
     */
    public function getDueVerifications($brandId)
    {
        $hour1Due = AiAction::where('brand_id', $brandId)
            ->where('verification_status', '!=', 'rolled_back')
            ->where('verified_hour_1', false)
            ->whereNotNull('verify_at_hour_1')
            ->where('verify_at_hour_1', '<=', now())
            ->get();

        $day1Due = AiAction::where('brand_id', $brandId)
            ->where('verification_status', '!=', 'rolled_back')
            ->where('verified_day_1', false)
            ->whereNotNull('verify_at_day_1')
            ->where('verify_at_day_1', '<=', now())
            ->get();

        $format = fn($actions, $phase) => $actions->map(fn($a) => [
            'action_id'      => $a->id,
            'action_name'    => $a->category,
            'action_title'   => $a->title,
            'action_key'     => $a->category,
            'phase'          => $phase,
            'metrics_before' => $a->metrics_at_execution,
            'executed_at'    => optional($a->executed_at)->toISOString(),
            'opportunity_type' => $a->category,
        ]);

        return response()->json([
            'brand_id' => $brandId,
            'hour_1'   => $format($hour1Due, 'hour_1'),
            'day_1'    => $format($day1Due, 'day_1'),
        ]);
    }

    public function recordVerification(Request $request)
    {
        $validated = $request->validate([
            'brand_id'           => 'required|integer|exists:brands,id',
            'action_id'          => 'required|integer|exists:ai_actions,id',
            'phase'              => 'required|in:immediate,hour_1,day_1,week_1',
            'metrics_before'     => 'nullable|array',
            'metrics_after'      => 'required|array',
            'metric_deltas'      => 'nullable|array',
            'was_successful'     => 'nullable|boolean',
            'improvement_score'  => 'nullable|numeric',
            'attribution'        => 'nullable|in:agent,human,mixed,unknown',
        ]);

        $action = AiAction::findOrFail($validated['action_id']);

        $verification = ActionVerification::updateOrCreate(
            ['action_id' => $action->id, 'phase' => $validated['phase']],
            [
                'brand_id'          => $validated['brand_id'],
                'metrics_before'    => $validated['metrics_before'] ?? $action->metrics_at_execution,
                'metrics_after'     => $validated['metrics_after'],
                'metric_deltas'     => $validated['metric_deltas'],
                'was_successful'    => $validated['was_successful'] ?? false,
                'improvement_score' => $validated['improvement_score'],
                'attribution'       => $validated['attribution'] ?? 'unknown',
                'verified_at'       => now(),
            ]
        );

        $phaseField = "verified_{$validated['phase']}";
        if (in_array($validated['phase'], ['immediate', 'hour_1', 'day_1'])) {
            $action->{$phaseField} = true;
        }

        if ($validated['phase'] === 'day_1') {
            $action->verification_status = ($validated['was_successful'] ?? false) ? 'verified' : 'failed';
        }

        $action->save();

        return response()->json([
            'success'         => true,
            'verification_id' => $verification->id,
            'phase'           => $validated['phase'],
            'was_successful'  => $verification->was_successful,
        ]);
    }

    public function rollbackAction(Request $request, $actionId)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $action = AiAction::findOrFail($actionId);

        $action->update([
            'verification_status' => 'rolled_back',
        ]);

        ActionVerification::create([
            'brand_id'         => $action->brand_id,
            'action_id'        => $action->id,
            'phase'            => 'rollback',
            'metrics_before'   => $action->metrics_at_execution,
            'metrics_after'    => null,
            'was_successful'   => false,
            'rollback_triggered' => true,
            'rollback_reason'  => $validated['reason'],
            'rollback_at'      => now(),
            'verified_at'      => now(),
        ]);

        Log::warning('Action rolled back', [
            'action_id' => $action->id,
            'reason'    => $validated['reason'],
        ]);

        return response()->json([
            'success'   => true,
            'action_id' => $action->id,
            'status'    => 'rolled_back',
        ]);
    }

    public function getBrief($brandId)
    {
        $brief = AiBrief::where('brand_id', $brandId)
            ->whereDate('brief_date', today())
            ->orderByDesc('created_at')
            ->first();

        if (!$brief) {
            return response()->json([
                'success' => false,
                'message' => 'No brief available for today yet.',
            ], 404);
        }

        $raw = $brief->raw_llm_output ?? [];

        return response()->json([
            'success' => true,
            'brief' => [
                'id'                       => $brief->id,
                'brief_date'               => $brief->brief_date->toDateString(),
                'strategic_diagnosis'      => $brief->strategic_diagnosis,
                'estimated_revenue_impact' => (float) $brief->estimated_revenue_impact,
                'confidence_score'         => (float) $brief->confidence_score,
                'ai_provider'              => $brief->ai_provider,
                'suggested_actions'        => $raw['actions'] ?? [],
                'is_approved'              => $brief->is_approved,
                'generated_at'             => $brief->created_at->toISOString(),
            ],
        ]);
    }

    public function getTourPackages($brandId)
    {
        $tours = TourPackage::forBrand((int) $brandId)
            ->active()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $tours->count(),
            'tours'   => $tours->map(fn($t) => [
                'id'              => $t->id,
                'name'            => $t->name,
                'slug'            => $t->slug,
                'destination'     => $t->destination,
                'country'         => $t->country,
                'duration_days'   => $t->duration_days,
                'price'           => (float) $t->price,
                'currency'        => $t->currency,
                'affiliate_url'   => $t->affiliate_url,
                'affiliate_network' => $t->affiliate_network,
                'keywords'        => $t->keywords,
                'description'     => $t->description,
            ])->values(),
        ]);
    }

    /**
     * Return the current metric snapshot for an action's category.
     * Single source of truth for verification. Reads real Laravel state,
     * not GA4 revenue which is unreliable for affiliate-led businesses.
     */
    public function getActionMetrics($brandId, $actionId)
    {
        $action = AiAction::where('brand_id', $brandId)->findOrFail($actionId);

        $metrics = match ($action->category) {
            'seo' => [
                'open_seo_issues'     => SeoIssue::where('brand_id', $brandId)
                    ->where('status', 'open')->count(),
                'resolved_seo_issues' => SeoIssue::where('brand_id', $brandId)
                    ->where('status', 'resolved')
                    ->where('resolved_at', '>=', $action->created_at)
                    ->count(),
            ],

            'content', 'social', 'email', 'web_copy' => [
                'published_drafts' => \App\Models\ContentDraft::where('brand_id', $brandId)
                    ->where('action_id', $action->id)
                    ->where('status', 'published')
                    ->count(),
                'drafts_total' => \App\Models\ContentDraft::where('brand_id', $brandId)
                    ->where('action_id', $action->id)
                    ->count(),
            ],

            'strategy', 'campaign' => [
                'leads_pending'   => \App\Models\Lead::where('brand_id', $brandId)
                    ->where('status', 'new')->count(),
                'leads_contacted' => \App\Models\Lead::where('brand_id', $brandId)
                    ->where('status', 'contacted')->count(),
                'leads_won'       => \App\Models\Lead::where('brand_id', $brandId)
                    ->where('status', 'won')->count(),
            ],

            default => [],
        };

        $affiliateNow = \App\Models\AffiliateData::where('brand_id', $brandId)
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->selectRaw('SUM(clicks) as clicks, SUM(bookings) as bookings,
                         SUM(commission_earned) as commission, SUM(revenue_generated) as revenue')
            ->first();

        $metrics['affiliate_7d_clicks']     = (int) ($affiliateNow->clicks ?? 0);
        $metrics['affiliate_7d_bookings']   = (int) ($affiliateNow->bookings ?? 0);
        $metrics['affiliate_7d_commission'] = (float) ($affiliateNow->commission ?? 0);
        $metrics['affiliate_7d_revenue']    = (float) ($affiliateNow->revenue ?? 0);

        $agentActed = \App\Models\GuardianAuditLog::where('brand_id', $brandId)
            ->where('event_type', 'agent_action_executed')
            ->where('metadata->action_id', $action->id)
            ->exists();

        $humanActed = \App\Models\GuardianAuditLog::where('brand_id', $brandId)
            ->where('event_type', 'seo_issue_resolved')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $action->created_at)
            ->exists();

        $attribution = match (true) {
            $agentActed && $humanActed => 'mixed',
            $agentActed                => 'agent',
            $humanActed                => 'human',
            default                    => 'unknown',
        };

        return response()->json([
            'brand_id'    => $brandId,
            'action_id'   => $action->id,
            'category'    => $action->category,
            'metrics'     => $metrics,
            'attribution' => $attribution,
            'as_of'       => now()->toISOString(),
        ]);
    }

    /**
     * Mark a stable_key's tracking rows resolved after a human escalation response.
     * Called by the Python agent when a human clicks "Resolve" on an escalation.
     */
    public function resolveOpportunity($brandId, $stableKey)
    {
        $count = AgentOpportunityTracking::forBrand($brandId)
            ->forStableKey($stableKey)
            ->whereIn('status', ['escalated', 'processing'])
            ->update(['status' => 'resolved', 'last_processed_at' => now()]);

        Log::info('Opportunity marked resolved by agent', [
            'brand_id'   => $brandId,
            'stable_key' => substr($stableKey, 0, 16) . '...',
            'rows'       => $count,
        ]);

        return response()->json([
            'success' => true,
            'resolved_count' => $count,
        ]);
    }
}