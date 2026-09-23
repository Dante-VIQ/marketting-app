<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [
        'https://www.vumbiventures.com',
        'https://vumbiventures.com',
        'https://saddlebrown-butterfly-192418.hostingersite.com',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'X-API-Key', 'X-Requested-With', 'Accept', 'Authorization'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];