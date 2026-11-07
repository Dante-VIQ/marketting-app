<?php

namespace App\Services\ContentPlan;

use App\Models\ContentIdea;
use App\Services\Analytics\AgentEvidenceService;
use App\Services\Competitors\CompetitorInsights;
use App\Services\Listening\ListeningInsights;
use App\Services\Personas\PersonaInsights;

class ContentPlanInsights
{
    private function postedRows(int $brandId): array
    {
        return ContentIdea::where('brand_id', $brandId)->where('status', 'posted')->get()->map(fn ($i) => [
            'title' => $i->title, 'platform' => $i->platform, 'format' => $i->format, 'topic' => $i->topic,
            'posted_at' => $i->posted_at?->toDateTimeString(), 'impressions' => $i->impressions, 'engagements' => $i->engagements,
        ])->all();
    }

    public function performance(int $brandId): array
    {
        return ContentPerformance::analyze($this->postedRows($brandId));
    }

    /** Suggestions from competitor gaps, listening, striking-distance keywords and persona pains. Each source is optional. */
    public function suggestions(int $brandId): array
    {
        $safe = function (callable $f, $default) {
            try { return $f(); } catch (\Throwable $e) { report($e); return $default; }
        };
        $gaps = $safe(fn () => app(CompetitorInsights::class)->summary($brandId)['topic_gaps'] ?? [], []);
        $conv = $safe(fn () => app(ListeningInsights::class)->summary($brandId)['conversations'] ?? [], []);
        $kw   = $safe(fn () => app(AgentEvidenceService::class)->seoSummary($brandId)['keywords']['striking_distance'] ?? [], []);
        $per  = $safe(fn () => app(PersonaInsights::class)->summary($brandId)['personas'] ?? [], []);

        // Real questions people asked under our posts (Meta comments, last 30 days).
        $questions = $safe(fn () => \App\Models\AudienceComment::where('brand_id', $brandId)->where('is_question', true)
            ->where('commented_at', '>=', now()->subDays(30))->orderByDesc('commented_at')->limit(5)->pluck('body')->all(), []);

        $existing = ContentIdea::where('brand_id', $brandId)->whereIn('status', ['idea', 'ready', 'posted'])->pluck('topic')->filter()->all();

        return ContentSuggester::suggest($gaps, $conv, $kw, $per, $existing, 12, $questions);
    }

    /** Compact block for the agent. */
    public function summary(int $brandId): array
    {
        $perf = $this->performance($brandId);
        $counts = ContentIdea::where('brand_id', $brandId)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();

        return [
            'plan' => ['ideas' => $counts['idea'] ?? 0, 'ready' => $counts['ready'] ?? 0, 'posted' => $counts['posted'] ?? 0],
            'performance' => [
                'state' => $perf['state'], 'measured_posts' => $perf['measured_posts'],
                'overall_engagement_rate_pct' => $perf['overall_engagement_rate'],
                'leaders' => $perf['leaders'],
                // Individual results with real numbers; descriptive only, not proof a format works.
                'top_posts' => ContentPerformance::topPosts($this->postedRows($brandId)),
            ],
            // What tagged posts did on the website (visits, key events). Descriptive; not revenue.
            'site_impact' => SocialSiteResults::summary($brandId),
            // Topics already planned or posted, so new suggestions do not repeat them.
            'planned_topics' => ContentIdea::where('brand_id', $brandId)->whereIn('status', ['idea', 'ready', 'posted'])->latest()->limit(15)->pluck('topic')->filter()->values()->all(),
            'note' => 'Posts are published by a human or scheduled for automatic publishing with human approval. Do not name a winning format unless performance.state is "ok".',
        ];
    }
}
