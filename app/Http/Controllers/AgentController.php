<?php

namespace App\Http\Controllers;

use App\Models\ActionVerification;
use App\Models\AgentExperience;
use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\SeoIssue;
use App\Services\AI\AiGatewayService;
use App\Services\AI\ContentGeneratorService;
use App\Services\Lead\LeadManagerService;
use App\Services\AI\SeoAssistantService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    protected $aiGateway;
    protected $seoAssistant;
    protected $leadService;
    protected $contentGenerator;

    public function __construct(
        AiGatewayService $aiGateway,
        SeoAssistantService $seoAssistant,
        LeadManagerService $leadService,
        ContentGeneratorService $contentGenerator
    ) {
        $this->aiGateway = $aiGateway;
        $this->seoAssistant = $seoAssistant;
        $this->leadService = $leadService;
        $this->contentGenerator = $contentGenerator;
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
        $brandId = $request->input('brandId');
        $topic = $request->input('topic');
        $template = $request->input('template', 'blog');

        $draft = $this->contentGenerator->generateContent($brandId, $topic, $template);

        return response()->json([
            'success' => true,
            'draft' => $draft,
            'contentId' => $draft->id,
        ]);
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
        $action = $request->except('brandId');

        // Log the action
        \App\Models\AiAction::create([
            'brand_id' => $brandId,
            'title' => $action['name'] ?? 'Unknown Action',
            'description' => json_encode($action),
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'action_received',
            'action' => $action,
            'actionId' => time(),
        ]);
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

    public function getSimilarExperiences(Request $request, $brandId)
    {
        $type = $request->input('type');
        $severity = $request->input('severity');
        $limit = $request->input('limit', 20);

        $query = AgentExperience::where('brand_id', $brandId);

        if ($type) {
            $query->where('opportunity_type', $type);
        }

        if ($severity) {
            $query->where('severity', $severity);
        }

        $experiences = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $total = $experiences->count();
        $successful = $experiences->where('was_successful', true)->count();
        $successRate = $total > 0 ? ($successful / $total) * 100 : 0;
        $avgImprovement = $experiences->where('was_successful', true)
            ->avg('improvement_percentage') ?? 0;

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
}
