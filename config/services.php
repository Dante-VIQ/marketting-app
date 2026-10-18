<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

'ahrefs' => [
    'token' => env('AHREFS_API_TOKEN'),
    'endpoint' => env('AHREFS_API_ENDPOINT', 'https://api.ahrefs.com/v3'),
    // Rows requested per brand per daily run (Ahrefs bills API units per row).
    'keyword_limit' => (int) env('AHREFS_KEYWORD_LIMIT', 100),
    'backlink_limit' => (int) env('AHREFS_BACKLINK_LIMIT', 100),
],

'competitors' => [
    'max_tracked'     => (int) env('COMPETITORS_MAX_TRACKED', 8),      // competitors kept per brand
    'gap_competitors' => (int) env('COMPETITORS_GAP_COMPETITORS', 3),  // how many get a keyword pull (Ahrefs units!)
    'gap_keywords'    => (int) env('COMPETITORS_GAP_KEYWORDS', 50),    // rows pulled per competitor
    'default_country' => env('COMPETITORS_DEFAULT_COUNTRY', 'US'),     // used when a brand has no ahrefs_country
    // Generic giants that "compete" for everything but are not real rivals.
    'ignore_domains'  => array_filter(array_map('trim', explode(',', env(
        'COMPETITORS_IGNORE_DOMAINS',
        'wikipedia.org,youtube.com,facebook.com,instagram.com,twitter.com,x.com,linkedin.com,pinterest.com,reddit.com,quora.com,amazon.com,google.com,tripadvisor.com,medium.com'
    )))),
],

];
