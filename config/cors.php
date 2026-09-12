<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'storage/*',
        'v1/*',
    ],

    'allowed_methods' => ['*'],

    // Allowed origins: reads from CORS_ALLOWED_ORIGINS (comma-separated), e.g. https://app.example.com
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    // ✅ Regex — matches any localhost port and all Cloudflare Pages (production + preview hashes)
    'allowed_origins_patterns' => [
        '#^http://localhost(:\d+)?$#',
        '#^http://127\.0\.0\.1(:\d+)?$#',
        '#^https://([a-zA-Z0-9-]+\.)?smile-lab-inv\.pages\.dev$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 86400,

    // ✅ Keep false — we're using Bearer tokens, not cookies
    'supports_credentials' => false,
];