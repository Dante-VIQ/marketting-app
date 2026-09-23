<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Action-Specific Verification Windows
    |--------------------------------------------------------------------------
    |
    | How long to wait before verifying each action type, per phase.
    | All values in seconds.
    |
    */
    'windows' => [
        'resolve_seo_issue' => [
            'immediate' => 300,      // 5 minutes
            'hour_1'    => 3600,     // 1 hour
            'day_1'     => 86400,    // 24 hours
            'metrics'   => ['organic_impressions', 'ranking_position', 'indexed_pages'],
            'success_threshold' => 0.05, // 5% improvement
        ],
        'run_site_scan' => [
            'immediate' => 60,
            'hour_1'    => 3600,
            'day_1'     => null,      // no day-1 verification
            'metrics'   => ['issues_detected', 'scan_duration'],
            'success_threshold' => 0.0,
        ],
        'trigger_content_generation' => [
            'immediate' => 60,
            'hour_1'    => 3600,
            'day_1'     => 86400,
            'metrics'   => ['impressions', 'clicks', 'conversion_rate'],
            'success_threshold' => 0.10,
        ],
        'notify_lead_response' => [
            'immediate' => 30,
            'hour_1'    => 3600,
            'day_1'     => 86400,
            'metrics'   => ['reply_rate', 'engagement'],
            'success_threshold' => 0.05,
        ],
        'pause_campaign' => [
            'immediate' => 60,
            'hour_1'    => 18000,
            'day_1'     => 180000,
            'metrics'   => ['ctr', 'spend', 'roi'],
            'success_threshold' => 0.10,
        ],
        'adjust_campaign' => [
            'immediate' => 60,
            'hour_1'    => 3600,
            'day_1'     => 86400,
            'metrics'   => ['ctr', 'spend', 'roi'],
            'success_threshold' => 0.10,
        ],
        'escalate_recurring_issue' => [
            'immediate' => null,     // no verification
            'hour_1'    => null,
            'day_1'     => null,
            'metrics'   => [],
            'success_threshold' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rollback Behavior
    |--------------------------------------------------------------------------
    */
    'rollback' => [
        // Automatically roll back if day-1 verification fails
        'auto_rollback_on_day_1_failure' => true,
        // Only roll back actions that are reversible
        'only_reversible' => true,
    ],
];