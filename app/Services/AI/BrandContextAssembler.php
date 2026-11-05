<?php

namespace App\Services\AI;

use App\Models\AiBrief;
use App\Models\Brand;
use App\Models\Competitor;
use App\Models\ContentIdea;
use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pre-assembles all brand context an AI service could need, then caches it.
 *
 * Problem this solves
 * -------------------
 * Without this class, each AI-calling service fetches its own slices of brand
 * data at call time: one service hits KnowledgeBase, another hits Competitor,
 * another hits ContentIdea — often redundantly within the same request cycle.
 * This costs both DB round-trips and tokens because the AI never receives the
 * full picture it needs to reason well.
 *
 * Solution
 * --------
 * Call BrandContextAssembler::assemble($brand, $purpose) once before any AI
 * call. It collects every relevant data slice (brief, knowledge, competitors,
 * performance, queue, policy, recent approvals), caches the bundle for 1 hour
 * per brand+purpose, and returns a plain array the service drops straight into
 * its prompt. The AI gets everything it needs in a single call; no back-and-
 * forth, no missing context, no duplicate DB queries within a request.
 *
 * Cache invalidation
 * ------------------
 * Tag: ['brand-context', 'brand-{id}']
 * Flush the whole brand's context whenever its core data changes:
 *   Cache::tags(['brand-context', "brand-{$brand->id}"])->flush();
 * This is called automatically by the BrandObserver (see below).
 *
 * Usage
 * -----
 *   $context = $assembler->assemble($brand, BrandContextAssembler::PURPOSE_CONTENT);
 *   // $context is an array; pass it to buildPrompt() or merge into your prompt
 *
 * Purposes
 * --------
 * Each purpose trims the bundle to what that kind of AI call actually needs,
 * keeping prompts lean while still eliminating round trips.
 *   PURPOSE_CONTENT   — content drafting (PostDrafter, ContentGeneratorService, …)
 *   PURPOSE_INTEL     — acquisition research (AcquisitionIntelligenceService)
 *   PURPOSE_BRIEF     — brief generation (BriefGeneratorService)
 *   PURPOSE_SCAN      — page scan analysis (RunPublicScanJob)
 *   PURPOSE_CHAT      — real-time bot (BotService)
 *   PURPOSE_GENERAL   — everything; use sparingly
 */
class BrandContextAssembler
{
    public const PURPOSE_CONTENT = 'content';
    public const PURPOSE_INTEL   = 'intel';
    public const PURPOSE_BRIEF   = 'brief';
    public const PURPOSE_SCAN    = 'scan';
    public const PURPOSE_CHAT    = 'chat';
    public const PURPOSE_GENERAL = 'general';

    /** TTL in seconds for the cached bundle (1 hour). */
    private const TTL = 3600;

    /**
     * Assemble (and cache) the brand context bundle for a given purpose.
     *
     * @param  Brand  $brand
     * @param  string $purpose  One of the PURPOSE_* constants
     * @return array            Context array, safe to pass directly into an AI prompt
     */
    public function assemble(Brand $brand, string $purpose = self::PURPOSE_GENERAL): array
    {
        $cacheKey = "brand-ctx:{$brand->id}:{$purpose}";

        try {
            return Cache::tags(['brand-context', "brand-{$brand->id}"])
                ->remember($cacheKey, self::TTL, function () use ($brand, $purpose) {
                    return $this->build($brand, $purpose);
                });
        } catch (\Throwable $e) {
            // Cache unavailable (e.g. Redis down) — build fresh, don't fail the request
            Log::warning('BrandContextAssembler: cache miss, building fresh', [
                'brand_id' => $brand->id,
                'purpose'  => $purpose,
                'error'    => $e->getMessage(),
            ]);
            return $this->build($brand, $purpose);
        }
    }

    /**
     * Flush the entire context cache for a brand.
     * Call this whenever the brand's brief, knowledge base, or competitors change.
     */
    public static function flush(Brand $brand): void
    {
        try {
            Cache::tags(['brand-context', "brand-{$brand->id}"])->flush();
        } catch (\Throwable $e) {
            Log::warning('BrandContextAssembler: flush failed', [
                'brand_id' => $brand->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ── Internal builder ────────────────────────────────────────────────────────

    private function build(Brand $brand, string $purpose): array
    {
        $ctx = [
            'brand_id'   => $brand->id,
            'brand_name' => $brand->name,
            'purpose'    => $purpose,
            'assembled_at' => now()->toIso8601String(),
        ];

        // Every purpose gets the knowledge block — it's always needed
        $ctx['knowledge'] = $this->knowledge($brand);

        // Slice by purpose to keep prompts lean
        match ($purpose) {
            self::PURPOSE_CONTENT => $ctx += $this->contentSlice($brand),
            self::PURPOSE_INTEL   => $ctx += $this->intelSlice($brand),
            self::PURPOSE_BRIEF   => $ctx += $this->briefSlice($brand),
            self::PURPOSE_SCAN    => $ctx += $this->scanSlice($brand),
            self::PURPOSE_CHAT    => $ctx += $this->chatSlice($brand),
            default               => $ctx += $this->generalSlice($brand),
        };

        return $ctx;
    }

    // ── Knowledge block ─────────────────────────────────────────────────────────

    /**
     * Core brand knowledge — always included, every purpose.
     * This is the single source of truth for voice, audience, pillars, etc.
     */
    private function knowledge(Brand $brand): array
    {
        $kb = KnowledgeBase::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->pluck('content', 'key')
            ->toArray();

        return [
            'voice'               => $kb['brand_voice'] ?? $brand->brand_voice ?? '',
            'business_description'=> $kb['business_description'] ?? '',
            'target_audience'     => $kb['target_audience'] ?? '',
            'content_pillars'     => $kb['content_pillars'] ?? '',
            'forbidden_topics'    => $kb['forbidden_topics'] ?? '',
            'usp'                 => $kb['usp'] ?? '',
            'tone_examples'       => $kb['tone_examples'] ?? '',
            'raw'                 => $kb,  // full KB for services that need specific keys
        ];
    }

    // ── Purpose slices ──────────────────────────────────────────────────────────

    private function contentSlice(Brand $brand): array
    {
        return [
            'competitors'  => $this->competitors($brand, 5),
            'queue'        => $this->currentQueue($brand),
            'history'      => $this->recentApprovals($brand, 10),
            'brand_policy' => $this->policy($brand),
        ];
    }

    private function intelSlice(Brand $brand): array
    {
        return [
            'competitors'  => $this->competitors($brand, 10),
            'performance'  => $this->performance($brand),
            'brief_digest' => $this->latestBriefDigest($brand),
        ];
    }

    private function briefSlice(Brand $brand): array
    {
        return [
            'competitors'  => $this->competitors($brand, 10),
            'performance'  => $this->performance($brand),
            'queue'        => $this->currentQueue($brand),
            'history'      => $this->recentApprovals($brand, 20),
        ];
    }

    private function scanSlice(Brand $brand): array
    {
        return [
            'brief_digest' => $this->latestBriefDigest($brand),
            'brand_policy' => $this->policy($brand),
        ];
    }

    private function chatSlice(Brand $brand): array
    {
        return [
            'brief_digest' => $this->latestBriefDigest($brand),
            'brand_policy' => $this->policy($brand),
        ];
    }

    private function generalSlice(Brand $brand): array
    {
        return [
            'competitors'  => $this->competitors($brand, 10),
            'performance'  => $this->performance($brand),
            'queue'        => $this->currentQueue($brand),
            'history'      => $this->recentApprovals($brand, 20),
            'brief_digest' => $this->latestBriefDigest($brand),
            'brand_policy' => $this->policy($brand),
        ];
    }

    // ── Data collectors ─────────────────────────────────────────────────────────

    /**
     * Active competitors — domain + name + why, trimmed for prompt size.
     */
    private function competitors(Brand $brand, int $limit = 5): array
    {
        return Competitor::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->limit($limit)
            ->get(['domain', 'name', 'notes'])
            ->map(fn ($c) => [
                'domain' => $c->domain,
                'name'   => $c->name,
                'notes'  => $c->notes ? mb_substr($c->notes, 0, 200) : null,
            ])
            ->all();
    }

    /**
     * Content ideas currently pending or scheduled — prevents the AI
     * from suggesting duplicates.
     */
    private function currentQueue(Brand $brand): array
    {
        return ContentIdea::where('brand_id', $brand->id)
            ->whereIn('status', ['draft', 'pending', 'scheduled'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['title', 'topic', 'platform', 'status', 'scheduled_at'])
            ->map(fn ($i) => [
                'title'    => $i->title,
                'topic'    => $i->topic,
                'platform' => $i->platform,
                'status'   => $i->status,
            ])
            ->all();
    }

    /**
     * Recently approved/posted ideas — gives the AI a sense of what
     * has already performed well (or not).
     */
    private function recentApprovals(Brand $brand, int $limit = 10): array
    {
        return ContentIdea::where('brand_id', $brand->id)
            ->whereIn('status', ['posted', 'approved'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['title', 'topic', 'platform', 'format', 'status',
                   'impressions', 'engagements', 'posted_at'])
            ->map(fn ($i) => [
                'title'       => $i->title,
                'topic'       => $i->topic,
                'platform'    => $i->platform,
                'impressions' => $i->impressions,
                'engagements' => $i->engagements,
            ])
            ->all();
    }

    /**
     * Recent performance snapshot — top-level numbers, not raw GA4 data.
     */
    private function performance(Brand $brand): array
    {
        // Pull from AnalyticsSnapshot if available; fall back to aggregated ContentIdea stats
        $topPosts = ContentIdea::where('brand_id', $brand->id)
            ->where('status', 'posted')
            ->whereNotNull('impressions')
            ->orderByDesc('impressions')
            ->limit(5)
            ->get(['title', 'platform', 'impressions', 'engagements'])
            ->map(fn ($i) => [
                'title'       => $i->title,
                'platform'    => $i->platform,
                'impressions' => $i->impressions,
                'engagements' => $i->engagements,
            ])
            ->all();

        $totalPosted = ContentIdea::where('brand_id', $brand->id)
            ->where('status', 'posted')
            ->count();

        return [
            'top_posts'     => $topPosts,
            'total_posted'  => $totalPosted,
            'summary'       => $totalPosted > 0
                ? "Brand has published {$totalPosted} pieces of content."
                : 'No content published yet.',
        ];
    }

    /**
     * Content safety/volume policy — the rules an AI must respect.
     */
    private function policy(Brand $brand): array
    {
        $config = $brand->config ?? [];
        return [
            'max_posts_per_day'    => $config['max_posts_per_day'] ?? 5,
            'publishing_enabled'   => (bool) ($config['publishing_enabled'] ?? false),
            'platforms_active'     => $config['platforms'] ?? [],
            'require_human_review' => true,  // always true — this is non-negotiable
        ];
    }

    /**
     * A lightweight summary of the latest approved brief — the AI's
     * "what should this brand do strategically" anchor.
     */
    private function latestBriefDigest(Brand $brand): ?array
    {
        $brief = AiBrief::where('brand_id', $brand->id)
            ->where('is_approved', true)
            ->orderByDesc('brief_date')
            ->first(['brief_date', 'strategic_diagnosis', 'confidence_score']);

        if (!$brief) {
            return null;
        }

        // Truncate the diagnosis so it doesn't eat the whole context window
        $diagnosis = $brief->strategic_diagnosis;
        if (is_array($diagnosis)) {
            $diagnosis = json_encode($diagnosis);
        }

        return [
            'date'       => $brief->brief_date?->toDateString(),
            'confidence' => $brief->confidence_score,
            'diagnosis'  => mb_substr((string) $diagnosis, 0, 600),
        ];
    }
}
