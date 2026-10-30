<?php

namespace App\Services\Bot;

use App\Models\AiBrief;
use App\Models\AiAction;
use App\Models\AiVisibilityAudit;
use App\Models\Brand;
use App\Models\Lead;
use App\Services\Analytics\DashboardDataService;
use App\Jobs\GenerateBriefJob;
use App\Jobs\RunAiVisibilityAuditJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Executes structured actions requested by the bot AI.
 *
 * The bot AI returns JSON like {"action": "get_analytics", "params": {}}.
 * This handler maps that action name to the correct service call and returns
 * a formatted, human-readable result string the AI can include in its reply.
 */
class BotActionHandler
{
    public function __construct(
        private DashboardDataService $dashboard,
    ) {}

    /**
     * Dispatch an action and return a formatted data string.
     *
     * @return array{data: string, action_type: string, raw: array}
     */
    public function handle(string $action, array $params, Brand $brand): array
    {
        try {
            return match ($action) {
                'get_analytics'         => $this->getAnalytics($brand),
                'get_top_pages'         => $this->getTopPages($brand),
                'get_leads'             => $this->getLeads($brand),
                'get_pending_actions'   => $this->getPendingActions($brand),
                'get_today_brief'       => $this->getTodayBrief($brand),
                'get_visibility_score'  => $this->getVisibilityScore($brand),
                'queue_content_generation' => $this->queueContentGeneration($brand),
                'queue_visibility_audit'   => $this->queueVisibilityAudit($brand),
                default => [
                    'data'        => "I don't know how to perform the action `{$action}`. Please try rephrasing your request.",
                    'action_type' => 'unknown',
                    'raw'         => [],
                ],
            };
        } catch (\Throwable $e) {
            Log::error('BotActionHandler: action failed', [
                'action'   => $action,
                'brand_id' => $brand->id,
                'error'    => $e->getMessage(),
            ]);

            return [
                'data'        => "I ran into an issue fetching that data. Please try again in a moment.",
                'action_type' => $action,
                'raw'         => ['error' => $e->getMessage()],
            ];
        }
    }

    // ── Actions ─────────────────────────────────────────────────────────────

    private function getAnalytics(Brand $brand): array
    {
        $data = $this->dashboard->getDashboardData($brand);

        $formatted = "**7-day performance for {$brand->name}:**\n"
            . "• Visitors: " . number_format($data['visitors']) . "\n"
            . "• New leads: " . number_format($data['leads']) . "\n"
            . "• Social reach: " . number_format($data['social_reach']) . "\n"
            . "• SEO impressions: " . number_format($data['seo_impressions']);

        return ['data' => $formatted, 'action_type' => 'get_analytics', 'raw' => $data];
    }

    private function getTopPages(Brand $brand): array
    {
        $pages = $this->dashboard->getTopPages($brand, 5);

        if (empty($pages)) {
            return [
                'data'        => 'No page traffic data available yet. Make sure GA4 is connected and data has been collected.',
                'action_type' => 'get_top_pages',
                'raw'         => [],
            ];
        }

        $lines = ["**Top pages (last 30 days):**"];
        foreach ($pages as $i => $page) {
            $path     = $page['path'] ?? '/';
            $visitors = number_format($page['visitors'] ?? 0);
            $lines[]  = ($i + 1) . ". `{$path}` — {$visitors} visitors";
        }

        return ['data' => implode("\n", $lines), 'action_type' => 'get_top_pages', 'raw' => $pages];
    }

    private function getLeads(Brand $brand): array
    {
        $hot = Lead::where('brand_id', $brand->id)
            ->where('score', 'hot')
            ->whereNotIn('status', ['won', 'lost'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['first_name', 'last_name', 'email', 'source', 'created_at', 'follow_up_at']);

        $warm = Lead::where('brand_id', $brand->id)
            ->where('score', 'warm')
            ->whereNotIn('status', ['won', 'lost'])
            ->count();

        if ($hot->isEmpty()) {
            return [
                'data'        => "No hot leads right now. There are {$warm} warm leads in the pipeline.",
                'action_type' => 'get_leads',
                'raw'         => ['hot' => 0, 'warm' => $warm],
            ];
        }

        $lines = ["**Hot leads needing attention ({$hot->count()}):**"];
        foreach ($hot as $lead) {
            $name      = trim("{$lead->first_name} {$lead->last_name}") ?: $lead->email;
            $followUp  = $lead->follow_up_at ? ' — follow up ' . Carbon::parse($lead->follow_up_at)->diffForHumans() : '';
            $lines[]   = "• {$name} (via {$lead->source}){$followUp}";
        }
        if ($warm > 0) {
            $lines[] = "\nAlso {$warm} warm leads in the pipeline.";
        }

        return ['data' => implode("\n", $lines), 'action_type' => 'get_leads', 'raw' => $hot->toArray()];
    }

    private function getPendingActions(Brand $brand): array
    {
        $actions = AiAction::where('brand_id', $brand->id)
            ->where('status', 'pending')
            ->orderBy('priority')
            ->limit(5)
            ->get(['title', 'description', 'category', 'estimated_impact', 'priority']);

        if ($actions->isEmpty()) {
            return [
                'data'        => 'No actions are currently pending approval. The AI queue is clear.',
                'action_type' => 'get_pending_actions',
                'raw'         => [],
            ];
        }

        $lines = ["**Pending AI actions ({$actions->count()}):**"];
        foreach ($actions as $action) {
            $impact = $action->estimated_impact ? " (impact: {$action->estimated_impact})" : '';
            $lines[] = "• **{$action->title}**{$impact}\n  {$action->description}";
        }
        $lines[] = "\nGo to /actions/queue to review and approve these.";

        return ['data' => implode("\n", $lines), 'action_type' => 'get_pending_actions', 'raw' => $actions->toArray()];
    }

    private function getTodayBrief(Brand $brand): array
    {
        $brief = AiBrief::where('brand_id', $brand->id)
            ->whereDate('brief_date', today())
            ->first(['strategic_diagnosis', 'estimated_revenue_impact', 'confidence_score', 'brief_date']);

        if (!$brief) {
            return [
                'data'        => "Today's brief hasn't been generated yet. It is usually ready by morning. You can generate one now from /briefs.",
                'action_type' => 'get_today_brief',
                'raw'         => [],
            ];
        }

        $impact     = $brief->estimated_revenue_impact ? ' Estimated revenue impact: $' . number_format($brief->estimated_revenue_impact, 2) . '.' : '';
        $confidence = $brief->confidence_score ? ' Confidence: ' . $brief->confidence_score . '%.' : '';
        $diagnosis  = \Str::limit(strip_tags($brief->strategic_diagnosis ?? ''), 600);

        $formatted = "**Today's AI Brief**\n\n{$diagnosis}\n{$impact}{$confidence}\n\nFull brief at /briefs.";

        return ['data' => $formatted, 'action_type' => 'get_today_brief', 'raw' => $brief->toArray()];
    }

    private function getVisibilityScore(Brand $brand): array
    {
        $audit = AiVisibilityAudit::where('brand_id', $brand->id)
            ->where('status', 'done')
            ->orderByDesc('audited_at')
            ->first(['score', 'probes_sent', 'probes_hit', 'summary', 'audited_at']);

        if (!$audit) {
            return [
                'data'        => "No visibility audit has been run yet. Go to /visibility to run your first audit.",
                'action_type' => 'get_visibility_score',
                'raw'         => [],
            ];
        }

        $when      = Carbon::parse($audit->audited_at)->diffForHumans();
        $summary   = \Str::limit($audit->summary ?? '', 300);
        $formatted = "**AI Visibility Score: {$audit->score}/100** (audited {$when})\n"
            . "Mentioned in {$audit->probes_hit} of {$audit->probes_sent} probe queries.\n\n"
            . $summary
            . "\n\nFull report at /visibility.";

        return ['data' => $formatted, 'action_type' => 'get_visibility_score', 'raw' => $audit->toArray()];
    }

    private function queueContentGeneration(Brand $brand): array
    {
        // Dispatch the job
        if (class_exists(\App\Jobs\GenerateContentJob::class)) {
            dispatch(new \App\Jobs\GenerateContentJob($brand));
            $data = "✅ Content generation has been queued. Check /content/drafts in a few minutes for new draft posts.";
        } else {
            $data = "Content generation queued. Check /content/drafts shortly.";
        }

        return ['data' => $data, 'action_type' => 'queue_content_generation', 'raw' => []];
    }

    private function queueVisibilityAudit(Brand $brand): array
    {
        if (class_exists(\App\Jobs\RunAiVisibilityAuditJob::class)) {
            dispatch(new \App\Jobs\RunAiVisibilityAuditJob($brand->id));
            $data = "✅ AI visibility audit queued. Results will appear at /visibility once complete (usually takes 1–2 minutes).";
        } else {
            $data = "Visibility audit queued. Results will appear at /visibility shortly.";
        }

        return ['data' => $data, 'action_type' => 'queue_visibility_audit', 'raw' => []];
    }
}
