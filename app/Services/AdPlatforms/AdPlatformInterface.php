<?php

namespace App\Services\AdPlatforms;

/**
 * One ad platform (Meta, Google Ads, TikTok, ...). Add a platform by implementing this
 * and registering it in config/ad_platforms.php; nothing else in the app changes.
 *
 * Money is always in the account's MAJOR unit (e.g. dollars) at this boundary;
 * each driver converts to the platform's own unit.
 */
interface AdPlatformInterface
{
    public function name(): string;

    /** True when the credentials needed to act are present in env. */
    public function isConfigured(): bool;

    /**
     * Current live state: ['status' => 'active'|'paused'|'other', 'daily_budget' => ?float, 'name' => ?string].
     *
     * @throws AdPlatformException
     */
    public function fetchCampaign(string $externalId): array;

    /** @throws AdPlatformException */
    public function pause(string $externalId): void;

    /** @throws AdPlatformException */
    public function resume(string $externalId): void;

    /** @throws AdPlatformException */
    public function setDailyBudget(string $externalId, float $amount): void;
}
