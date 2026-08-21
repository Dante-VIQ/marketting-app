<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Types
    |--------------------------------------------------------------------------
    */
    'domain_types' => [
        'marketing' => [
            'label' => 'Marketing',
            'icon' => '📈',
            'description' => 'SEO, content, social media, and client acquisition',
            'default_timezone' => 'Africa/Nairobi',
            'required_config_keys' => ['ga4_property_id', 'ga4_measurement_id', 'ga4_api_secret'],
            'optional_config_keys' => ['facebook_page_id', 'facebook_access_token', 'linkedin_company_id', 'linkedin_access_token'],
        ],
        'healthcare' => [
            'label' => 'Healthcare',
            'icon' => '🏥',
            'description' => 'Symptom analysis, referrals, and emergency detection',
            'default_timezone' => 'Africa/Nairobi',
            'required_config_keys' => ['fhir_endpoint', 'api_key'],
            'optional_config_keys' => ['hospital_id', 'department'],
        ],
        'education' => [
            'label' => 'Education',
            'icon' => '🎓',
            'description' => 'Student analytics, teacher support, and parent engagement',
            'default_timezone' => 'Africa/Nairobi',
            'required_config_keys' => ['lms_url', 'api_key'],
            'optional_config_keys' => ['school_id', 'grade_levels'],
        ],
        'youth' => [
            'label' => 'Youth Development',
            'icon' => '🌟',
            'description' => 'Skills identification, career guidance, and growth tracking',
            'default_timezone' => 'Africa/Nairobi',
            'required_config_keys' => [],
            'optional_config_keys' => ['program_id', 'cohort_id'],
        ],
        'general' => [
            'label' => 'General',
            'icon' => '📋',
            'description' => 'Generic AI workforce for any domain',
            'default_timezone' => 'Africa/Nairobi',
            'required_config_keys' => [],
            'optional_config_keys' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'timezone' => 'Africa/Nairobi',
        'brand_voice' => 'Professional, helpful, and trustworthy. Speak clearly and avoid jargon.',
        'is_active' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'owner' => 'Full control over the brand and all settings',
        'admin' => 'Can manage content and settings but cannot delete the brand',
        'editor' => 'Can create and edit content',
        'viewer' => 'Read-only access to dashboards and reports',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'manage-brand' => 'Full control over brand settings',
        'manage-content' => 'Create, edit, publish content',
        'manage-campaigns' => 'Manage campaigns and budgets',
        'view-analytics' => 'View analytics and reports',
        'manage-users' => 'Invite and manage team members',
        'manage-ai' => 'Configure AI settings',
    ],
];