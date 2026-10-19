<?php

/*
| Meta (Facebook + Instagram) organic integration: Pages, Instagram, insights, publishing.
| Credentials come from .env only. The ads integration keeps its own config (config/ad_platforms.php)
| but reads the same token when META_ACCESS_TOKEN is not set separately.
*/
return [
    'access_token' => env('META_ACCESS_TOKEN') ?: env('META_ADS_ACCESS_TOKEN'),
    // Graph API versions expire about two years after release; v20 was removed in Sept 2026.
    'api_version'  => env('META_API_VERSION') ?: env('META_ADS_API_VERSION') ?: 'v25.0',

    // Optional: lets the Meta page inspect the token (scopes, expiry) with an app token.
    'app_id'     => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),

    // Default Page / Instagram account when a brand does not set meta_page_id / meta_instagram_id in its config.
    'page_id'      => env('META_PAGE_ID'),
    'instagram_id' => env('META_INSTAGRAM_ID'),

    // Ad account for ad reporting, e.g. act_1234567890 (or just the number). Brand config key: meta_ad_account_id.
    'ad_account_id' => env('META_AD_ACCOUNT_ID'),
    // Country codes used when sizing an audience.
    'audience_countries' => array_filter(array_map('trim', explode(',', (string) env('META_AUDIENCE_COUNTRIES', 'KE')))),

    'publishing' => [
        // Master switch. Nothing is ever posted automatically unless this is true AND a human approved the post.
        'enabled'      => (bool) env('META_PUBLISHING_ENABLED', false),
        'max_per_day'  => (int) env('META_MAX_POSTS_PER_DAY', 5),   // per brand
    ],

    // Meta retired most reach/impression metrics in June 2026 in favour of "media views".
    // Names live here so a future rename is a config change, not a code change.
    'insights' => [
        'facebook_post' => ['post_media_view', 'post_total_media_view_unique', 'post_clicks'],
        'instagram_media' => ['views', 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions'],
        'facebook_page' => ['page_total_media_view_unique', 'page_media_view', 'page_post_engagements', 'page_follows'],
        'instagram_account' => ['reach', 'views', 'follower_count', 'accounts_engaged'],
    ],
];
