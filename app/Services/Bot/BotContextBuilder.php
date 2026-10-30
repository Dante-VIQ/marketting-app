<?php

namespace App\Services\Bot;

use App\Models\AiBrief;
use App\Models\AiAction;
use App\Models\Brand;
use App\Models\Lead;
use App\Models\User;
use App\Services\Analytics\DashboardDataService;
use App\Services\BrandContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Builds the system prompt that scopes every bot conversation.
 *
 * The prompt tells the AI:
 *  - Who it is and what it can do
 *  - Everything about the brand it is serving
 *  - A live snapshot of the business (analytics, leads, pending actions)
 *  - How to format structured action requests
 *
 * Keeping this logic here means BotService stays thin.
 */
class BotContextBuilder
{
    public function __construct(
        private BrandContextService  $brandContext,
        private DashboardDataService $dashboard,
    ) {}

    /**
     * Build the full system prompt for a bot session.
     */
    public function build(Brand $brand, User $user): string
    {
        $brandCtx   = $this->brandContext->getFullContext($brand);
        $liveData   = $this->getLiveSnapshot($brand);
        $userRole   = $this->getUserRole($brand, $user);
        $todayDate  = Carbon::now()->setTimezone($brand->timezone ?? 'Africa/Nairobi')->format('l, F j Y');

        return <<<PROMPT
You are the Vumbi AI assistant for **{$brand->name}**.
Today is {$todayDate}. You are speaking with {$user->name}, who has the role of **{$userRole}**.

## Your purpose
You help users understand and operate their business through conversation.
You retrieve real data, explain what it means, and help them decide what to do next.
You are NOT a general chatbot — stay focused on this brand's business.

## Brand profile
- **Name:** {$brand->name}
- **Website:** {$brand->website_url}
- **Type:** {$brandCtx['domain_label']}
- **Brand voice:** {$brandCtx['brand_voice']}
{$this->formatPageContext($brandCtx)}

## Live business snapshot
{$liveData}

## What you can do
When a user asks for information or wants to take an action, respond with either:

1. **A plain conversational answer** — for questions you can answer from context
2. **A structured action request** — when you need to fetch live data or queue a task

For a structured action, your ENTIRE reply must be valid JSON in this exact shape:
```json
{"action": "<action_name>", "params": {}, "message": "Fetching that for you…"}
```

Available actions:
- `get_analytics` — 7-day visitors, leads, social reach, SEO impressions
- `get_top_pages` — top performing pages by traffic
- `get_leads` — hot leads needing attention
- `get_pending_actions` — AI-recommended actions waiting for approval
- `get_today_brief` — today's strategic AI brief
- `get_visibility_score` — latest AI visibility audit score
- `queue_content_generation` — queue a content generation run (requires confirmation)
- `queue_visibility_audit` — queue a fresh AI visibility audit (requires confirmation)

## Rules
- Only use actions listed above. Never invent action names.
- For actions that change data (queue_*), always confirm with the user before triggering.
- Respect the user's role — {$userRole}s {$this->getRoleCapabilityNote($userRole)}.
- Be concise. Business people are busy. Get to the point.
- When data shows a problem, say so clearly and suggest the most important next step.
- Never fabricate numbers. If you don't have data, say so and offer to fetch it.
PROMPT;
    }

    /**
     * Build a lighter system prompt for the onboarding guide mode.
     * Used when a user has no brand yet.
     */
    public function buildOnboardingPrompt(User $user): string
    {
        return <<<PROMPT
You are the Vumbi AI setup assistant. You are helping {$user->name} set up their brand on the platform.

Guide them step by step through creating their first brand. Ask one question at a time.
You need to collect:
1. Brand name
2. Website URL (optional but recommended)
3. Brand type (marketing, travel, healthcare, education, youth, or general)
4. Brand voice — how they want to sound to their audience (ask them to describe their brand in 2–3 sentences)
5. Their primary goal: grow social following, increase website traffic, generate leads, or beat competitors

Be warm, encouraging, and explain why each piece of information matters.
Once you have all five, confirm everything back to them and tell them to click "Get Started" on the brand creation page.

Do not attempt to create the brand yourself — the form does that. Your job is to help them understand what to fill in.
PROMPT;
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function getLiveSnapshot(Brand $brand): string
    {
        try {
            $data = $this->dashboard->getDashboardData($brand);

            $pendingActions = AiAction::where('brand_id', $brand->id)
                ->where('status', 'pending')
                ->count();

            $hotLeads = Lead::where('brand_id', $brand->id)
                ->where('score', 'hot')
                ->whereNotIn('status', ['won', 'lost'])
                ->count();

            $todayBrief = AiBrief::where('brand_id', $brand->id)
                ->whereDate('brief_date', today())
                ->value('strategic_diagnosis');

            $briefLine = $todayBrief
                ? '- **Today\'s brief:** ' . \Str::limit(strip_tags($todayBrief), 200)
                : '- **Today\'s brief:** Not generated yet';

            return <<<SNAPSHOT
- **Visitors (7 days):** {$data['visitors']}
- **Leads (7 days):** {$data['leads']}
- **Social reach (7 days):** {$data['social_reach']}
- **SEO impressions (7 days):** {$data['seo_impressions']}
- **Hot leads awaiting attention:** {$hotLeads}
- **Pending AI actions:** {$pendingActions}
{$briefLine}
SNAPSHOT;
        } catch (\Throwable $e) {
            Log::warning('BotContextBuilder: failed to load live snapshot', [
                'brand_id' => $brand->id,
                'error'    => $e->getMessage(),
            ]);
            return '- Live data temporarily unavailable.';
        }
    }

    private function formatPageContext(array $brandCtx): string
    {
        $count = $brandCtx['pages_scanned_count'] ?? 0;
        if ($count === 0) {
            return '';
        }

        $topics = implode(', ', array_slice($brandCtx['all_topics'] ?? [], 0, 10));
        $types  = implode(', ', $brandCtx['page_types_found'] ?? []);

        return "- **Pages scanned:** {$count} ({$types})\n- **Key topics:** {$topics}";
    }

    private function getUserRole(Brand $brand, User $user): string
    {
        try {
            $role = $user->roles()
                ->where('brand_id', $brand->id)
                ->first();
            return $role?->name ?? 'viewer';
        } catch (\Throwable) {
            return 'viewer';
        }
    }

    private function getRoleCapabilityNote(string $role): string
    {
        return match ($role) {
            'owner', 'admin' => 'can view all data and queue actions',
            'editor'         => 'can view data but cannot queue destructive actions',
            default          => 'have read-only access to data',
        };
    }
}
