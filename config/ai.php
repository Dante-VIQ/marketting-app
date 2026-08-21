<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'ollama' => [
            'model' => env('OLLAMA_MODEL', 'qwen3:4b'),
            'endpoint' => env('OLLAMA_ENDPOINT', 'http://192.168.1.5:11434/api/generate'),
        ],
        'openai' => [
            'model' => env('OPENAI_MODEL', 'gpt-4'),
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
        ],
        'anthropic' => [
            'model' => env('ANTHROPIC_MODEL', 'claude-3-opus-20240229'),
            'endpoint' => 'https://api.anthropic.com/v1/messages',
        ],
        'gemini' => [
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default AI Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'temperature' => 0.7,
        'max_tokens' => 4096,
    ],
];
