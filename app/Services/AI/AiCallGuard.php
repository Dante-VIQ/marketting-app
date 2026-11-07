<?php

namespace App\Services\AI;

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\ContentIdea;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pre-flight guard for AI calls.
 *
 * Problem
 * -------
 * AI calls are slow and rate-limited. Many of the decisions they make can be
 * answered instantly with a SQL query or a string comparison — no LLM needed.
 * Without a gate, every request hits the AI even when the answer is obvious.
 *
 * Solution
 * --------
 * Call AiCallGuard::check() before any AI invocation. It runs every
 * deterministic check in order (fastest first, DB queries last) and returns
 * on the first failure. If all checks pass, the AI call proceeds normally.
 *
 * Usage
 * -----
 *   $guard = new AiCallGuard();
 *   $result = $guard->check($brand, $idea);   // ContentIdea path
 *   // or
 *   $result = $guard->checkDraft($brand, $topic, $platform);  // topic/platform path
 *
 *   if (!$result['allowed']) {
 *       // $result['reason']  — human-readable explanation
 *       // $result['code']    — machine-readable skip code
 *       return null;  // skip the AI call entirely
 *   }
 *
 * Skip codes
 * ----------
 *   brand_inactive          — brand is not active
 *   platform_disabled       — platform not enabled for this brand
 *   duplicate_idea          — identical title/topic already in queue or posted
 *   already_has_copy        — idea already has drafted copy
 *   recently_posted_topic   — same topic posted within cooldown window
 *   schedule_conflict       — platform already has a post scheduled for today
 *   daily_draft_limit       — brand hit the daily AI generation cap
 *   hourly_draft_limit      — brand hit the hourly AI generation cap
 *   publishing_disabled     — META_PUBLISHING_ENABLED=false (for publish-path calls)
 */
class AiCallGuard
{
    /** Max posts per platform per day before we stop suggesting new ones. */
    private const MAX_PER_PLATFORM_PER_DAY = 3;

    /** Hours before the same topic can be drafted again on the same platform. */
    private const TOPIC_COOLDOWN_HOURS = 48;

    /** Max AI-generated drafts per brand per hour. */
    private const HOURLY_LIMIT = 10;

    /** Max AI-generated drafts per brand per day. */
    private const DAILY_LIMIT  = 50;

    // ── Public API ──────────────────────────────────────────────────────────────

    /**
     * Full check for a ContentIdea — most common path.
     *
     * @return array{allowed: bool, reason: string|null, code: string|null, checks_run: string[]}
     */
    public function check(Brand $brand, ContentIdea $idea): array
    {
        $checks = [];

        // 1. Brand active? — zero DB hits, single property read
        if (!$brand->is_active) {
            return $this->deny('brand_inactive', 'Brand is not active.', $checks);
        }
        $checks[] = 'brand_active';

        // 2. Already has copy? — no need to call AI again for same idea
        if (!empty(trim((string) $idea->copy))) {
            return $this->deny('already_has_copy', 'This idea already has drafted copy.', $checks);
        }
        $checks[] = 'no_existing_copy';

        // 3. Platform enabled for this brand?
        $platformCheck = $this->platformEnabled($brand, $idea->platform);
        if (!$platformCheck['ok']) {
            return $this->deny('platform_disabled', $platformCheck['reason'], $checks);
        }
        $checks[] = 'platform_enabled';

        // 4. Exact duplicate in queue? (same title on same platform, any active status)
        if ($this->isDuplicate($brand->id, $idea->title, $idea->platform, $idea->id)) {
            return $this->deny(
                'duplicate_idea',
                "An idea with the same title already exists for {$idea->platform}.",
                $checks
            );
        }
        $checks[] = 'no_duplicate';

        // 5. Same topic posted recently on same platform?
        if ($idea->topic && $this->recentlyPosted($brand->id, $idea->topic, $idea->platform)) {
            return $this->deny(
                'recently_posted_topic',
                "This topic was already posted on {$idea->platform} within the last " . self::TOPIC_COOLDOWN_HOURS . " hours.",
                $checks
            );
        }
        $checks[] = 'topic_cooldown_clear';

        // 6. Platform already at daily post cap?
        if ($this->platformAtDailyCap($brand->id, $idea->platform)) {
            return $this->deny(
                'schedule_conflict',
                "{$idea->platform} already has " . self::MAX_PER_PLATFORM_PER_DAY . " posts scheduled or posted today.",
                $checks
            );
        }
        $checks[] = 'platform_cap_clear';

        // 7. AI generation rate limits (hourly then daily — cheaper query first)
        $rateCheck = $this->rateLimit($brand->id);
        if (!$rateCheck['ok']) {
            return $this->deny($rateCheck['code'], $rateCheck['reason'], $checks);
        }
        $checks[] = 'rate_limit_clear';

        return $this->allow($checks);
    }

    /**
     * Lighter check for topic/platform strings (ContentGeneratorService, PostDrafter).
     * Skips idea-specific checks (already_has_copy, exact duplicate by id).
     *
     * @return array{allowed: bool, reason: string|null, code: string|null, checks_run: string[]}
     */
    public function checkDraft(Brand $brand, string $topic, string $platform): array
    {
        $checks = [];

        if (!$brand->is_active) {
            return $this->deny('brand_inactive', 'Brand is not active.', $checks);
        }
        $checks[] = 'brand_active';

        $platformCheck = $this->platformEnabled($brand, $platform);
        if (!$platformCheck['ok']) {
            return $this->deny('platform_disabled', $platformCheck['reason'], $checks);
        }
        $checks[] = 'platform_enabled';

        if ($this->recentlyPosted($brand->id, $topic, $platform)) {
            return $this->deny(
                'recently_posted_topic',
                "This topic was already posted on {$platform} within the last " . self::TOPIC_COOLDOWN_HOURS . " hours.",
                $checks
            );
        }
        $checks[] = 'topic_cooldown_clear';

        if ($this->platformAtDailyCap($brand->id, $platform)) {
            return $this->deny(
                'schedule_conflict',
                "{$platform} already has " . self::MAX_PER_PLATFORM_PER_DAY . " posts scheduled or posted today.",
                $checks
            );
        }
        $checks[] = 'platform_cap_clear';

        $rateCheck = $this->rateLimit($brand->id);
        if (!$rateCheck['ok']) {
            return $this->deny($rateCheck['code'], $rateCheck['reason'], $checks);
        }
        $checks[] = 'rate_limit_clear';

        return $this->allow($checks);
    }

    // ── Individual checks ───────────────────────────────────────────────────────

    /**
     * Is this platform configured as active for the brand?
     * Reads from brand config; passes through if config is absent (permissive default).
     */
    private function platformEnabled(Brand $brand, ?string $platform): array
    {
        if (!$platform) {
            return ['ok' => true, 'reason' => null];
        }

        $config    = $brand->config ?? [];
        $platforms = $config['platforms'] ?? [];

        // If no platform config is set, treat all as enabled (backward-compatible)
        if (empty($platforms)) {
            return ['ok' => true, 'reason' => null];
        }

        $enabled = $platforms[$platform]['enabled']
            ?? $platforms[$platform]           // might be a simple bool
            ?? true;

        if (!$enabled) {
            return ['ok' => false, 'reason' => "{$platform} is not enabled for this brand."];
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * Is there already an idea with the same title on the same platform
     * that isn't this idea (by id) and isn't rejected/archived?
     */
    private function isDuplicate(int $brandId, ?string $title, ?string $platform, ?int $excludeId = null): bool
    {
        if (!$title || !$platform) {
            return false;
        }

        $q = ContentIdea::where('brand_id', $brandId)
            ->where('platform', $platform)
            ->whereRaw('LOWER(title) = ?', [strtolower(trim($title))])
            ->whereNotIn('status', ['rejected', 'archived']);

        if ($excludeId) {
            $q->where('id', '!=', $excludeId);
        }

        return $q->exists();
    }

    /**
     * Was the same topic posted (or approved) on this platform
     * within the cooldown window?
     */
    private function recentlyPosted(int $brandId, string $topic, ?string $platform): bool
    {
        if (!$platform) {
            return false;
        }

        $since = Carbon::now()->subHours(self::TOPIC_COOLDOWN_HOURS);

        return ContentIdea::where('brand_id', $brandId)
            ->where('platform', $platform)
            ->whereRaw('LOWER(topic) = ?', [strtolower(trim($topic))])
            ->whereIn('status', ['posted', 'approved', 'scheduled'])
            ->where('updated_at', '>=', $since)
            ->exists();
    }

    /**
     * Has this platform already hit its daily post cap for this brand?
     * Counts scheduled + posted ideas for today.
     */
    private function platformAtDailyCap(int $brandId, ?string $platform): bool
    {
        if (!$platform) {
            return false;
        }

        $count = ContentIdea::where('brand_id', $brandId)
            ->where('platform', $platform)
            ->whereIn('status', ['posted', 'scheduled', 'approved'])
            ->whereDate('updated_at', Carbon::today())
            ->count();

        return $count >= self::MAX_PER_PLATFORM_PER_DAY;
    }

    /**
     * Hourly and daily AI-draft rate limits (SQL count on ContentDraft).
     */
    private function rateLimit(int $brandId): array
    {
        $hourly = ContentDraft::where('brand_id', $brandId)
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();

        if ($hourly >= self::HOURLY_LIMIT) {
            return [
                'ok'     => false,
                'code'   => 'hourly_draft_limit',
                'reason' => "Hourly AI generation limit reached ({$hourly}/" . self::HOURLY_LIMIT . "). Try again in a few minutes.",
            ];
        }

        $daily = ContentDraft::where('brand_id', $brandId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($daily >= self::DAILY_LIMIT) {
            return [
                'ok'     => false,
                'code'   => 'daily_draft_limit',
                'reason' => "Daily AI generation limit reached ({$daily}/" . self::DAILY_LIMIT . ").",
            ];
        }

        return ['ok' => true, 'code' => null, 'reason' => null];
    }

    // ── Result builders ─────────────────────────────────────────────────────────

    private function deny(string $code, string $reason, array $checks): array
    {
        Log::info('AiCallGuard: call blocked', [
            'code'       => $code,
            'reason'     => $reason,
            'checks_run' => $checks,
        ]);

        return [
            'allowed'    => false,
            'reason'     => $reason,
            'code'       => $code,
            'checks_run' => $checks,
        ];
    }

    private function allow(array $checks): array
    {
        return [
            'allowed'    => true,
            'reason'     => null,
            'code'       => null,
            'checks_run' => $checks,
        ];
    }
}
