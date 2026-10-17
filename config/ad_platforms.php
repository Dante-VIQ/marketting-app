<?php

/*
| Ad platforms the agent may act on. Credentials come from .env only.
| To add a platform: implement App\Services\AdPlatforms\AdPlatformInterface,
| add it to `drivers`, and add its config block below.
*/
return [

    'drivers' => [
        'meta' => \App\Services\AdPlatforms\MetaAdsPlatform::class,
    ],

    // Shown on the Ad Platforms page. `env` lists the variables to put in .env (never the values).
    'labels' => [
        'meta' => ['label' => 'Meta (Facebook & Instagram) Ads', 'env' => ['META_ADS_ACCESS_TOKEN'], 'id_hint' => 'Numeric campaign id from Ads Manager'],
    ],

    'meta' => [
        'access_token'    => env('META_ADS_ACCESS_TOKEN'),
        'api_version'     => env('META_ADS_API_VERSION', 'v21.0'),
        // 100 for cents-based currencies (USD, EUR...); 1 for zero-decimal (e.g. JPY).
        'currency_offset' => (int) env('META_ADS_CURRENCY_OFFSET', 100),
    ],

    'safety' => [
        // A single agent action may not change a daily budget by more than this fraction.
        'max_budget_change' => (float) env('AD_MAX_BUDGET_CHANGE', 0.25),
    ],
];
