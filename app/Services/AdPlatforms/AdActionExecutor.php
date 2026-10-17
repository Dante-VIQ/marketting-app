<?php

namespace App\Services\AdPlatforms;

use App\Models\AiAction;
use App\Models\Campaign;

/**
 * Carries out an APPROVED campaign action on the real ad platform and records
 * the live state captured just before acting, so it can be reversed exactly.
 */
class AdActionExecutor
{
    public function __construct(private AdPlatformRegistry $registry)
    {
    }

    /** True when this action has what it needs to run on a platform. */
    public function canExecute(AiAction $action): bool
    {
        $campaign = $action->campaign_id ? Campaign::find($action->campaign_id) : null;

        return $action->ad_operation
            && $campaign && $campaign->platform && $campaign->external_id
            && $this->registry->has($campaign->platform)
            && $this->registry->get($campaign->platform)->isConfigured();
    }

    /** @throws AdPlatformException */
    public function execute(AiAction $action): array
    {
        $campaign = Campaign::where('brand_id', $action->brand_id)->find($action->campaign_id)
            ?? throw new AdPlatformException('Campaign not found for this brand.');

        $platform = $this->registry->get((string) $campaign->platform);
        $id       = (string) $campaign->external_id;
        $before   = $platform->fetchCampaign($id);   // fail here = nothing changed, nothing to reverse

        $op = $action->ad_operation;
        switch ($op) {
            case 'pause':
                $platform->pause($id);
                $campaign->update(['status' => 'paused']);
                break;
            case 'resume':
                $platform->resume($id);
                $campaign->update(['status' => 'active']);
                break;
            case 'set_budget':
                $new = (float) ($action->ad_params['daily_budget'] ?? 0);
                $max = (float) config('ad_platforms.safety.max_budget_change', 0.25);
                self::assertBudgetChangeAllowed($before['daily_budget'], $new, $max);
                $platform->setDailyBudget($id, $new);
                break;
            default:
                throw new AdPlatformException("Unsupported ad operation '{$op}'.");
        }

        $action->update(['reversal_data' => [
            'platform' => $campaign->platform, 'external_id' => $id, 'operation' => $op, 'before' => $before,
        ]]);

        return ['platform' => $campaign->platform, 'operation' => $op, 'before' => $before];
    }

    /**
     * Undo an executed action using the state captured before it ran.
     *
     * @throws AdPlatformException
     */
    public function reverse(AiAction $action): array
    {
        $r = $action->reversal_data;
        if (!$r) {
            throw new AdPlatformException('Nothing to reverse: no state was captured for this action.');
        }
        if ($action->reversed_at) {
            return ['already_reversed' => true];
        }

        $platform = $this->registry->get($r['platform']);
        $id       = $r['external_id'];
        $before   = $r['before'];
        $campaign = Campaign::where('platform', $r['platform'])->where('external_id', $id)->first();

        switch ($r['operation']) {
            case 'pause':
                if (($before['status'] ?? null) === 'active') {
                    $platform->resume($id);
                    $campaign?->update(['status' => 'active']);
                }
                break;
            case 'resume':
                if (($before['status'] ?? null) === 'paused') {
                    $platform->pause($id);
                    $campaign?->update(['status' => 'paused']);
                }
                break;
            case 'set_budget':
                if (($before['daily_budget'] ?? null) !== null) {
                    $platform->setDailyBudget($id, (float) $before['daily_budget']);
                }
                break;
        }

        $action->update(['reversed_at' => now()]);

        return ['reversed' => $r['operation'], 'restored' => $before];
    }

    /** Pure guard: a positive budget, and no change bigger than $maxFraction of the current one. */
    public static function assertBudgetChangeAllowed(?float $current, float $new, float $maxFraction): void
    {
        if ($new <= 0) {
            throw new AdPlatformException('Daily budget must be greater than zero.');
        }
        if ($current === null || $current <= 0) {
            throw new AdPlatformException('Current daily budget is unknown (not a campaign-budget campaign); refusing to guess.');
        }
        if (abs($new - $current) / $current > $maxFraction + 1e-9) {
            $pct = round($maxFraction * 100);
            throw new AdPlatformException("Budget change from {$current} to {$new} exceeds the {$pct}% per-action limit.");
        }
    }
}
