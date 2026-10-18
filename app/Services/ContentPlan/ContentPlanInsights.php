<?php

namespace App\Services\ContentPlan;

use App\Models\ContentIdea;
use App\Services\Analytics\AgentEvidenceService;
use App\Services\Competitors\CompetitorInsights;
use App\Services\Listening\ListeningInsights;
use App\Services\Personas\PersonaInsights;

class ContentPlanInsights
{
    public function performance(int $brandId): array
    {
        $posts = ContentIdea::where('brand_id', $brandId)->where('status', 'posted')->get()->map(fn ($i) => [
            'platform' => $i->platform, 'format' => $i->format, 'topic' => $i->topic,
            'posted_at' => $i->posted_at?->toDateTimeString(), 'impressions' => $i->impressions, 'engagements' => $i->engagements,
        ])->all();

        return ContentPerformance::analyze($posts);
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

        $existing = ContentIdea::where('brand_id', $brandId)->whereIn('status', ['idea', 'ready', 'posted'])->pluck('topic')->filter()->all();

        return ContentSuggester::suggest($gaps, $conv, $kw, $per, $existing);
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
            ],
            'note' => 'Posting is done manually by a human. Never recommend automated publishing. Do not name a winning format unless performance.state is "ok".',
        ];
    }
}
