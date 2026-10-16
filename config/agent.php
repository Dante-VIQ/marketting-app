<?php
return [
    // Shared key: grants access to every brand. Keep during rollout, then set
    // AGENT_ALLOW_SHARED_KEY=false once every agent uses a per-brand key.
    'api_key' => env('LARAVEL_API_KEY'),
    'allow_shared_key' => (bool) env('AGENT_ALLOW_SHARED_KEY', true),
];
