<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        // Production
        'https://vikashana.com',
        'https://app.vikashana.com',
        'https://superadmin.vikashana.com',
        // Local development
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
    ],
    'allowed_origins_patterns' => [
        '#^http://192\.168\..*#',   // local network (phone on WiFi)
        '#^http://10\..*#',         // alternate local network range
        '#^http://172\..*#',        // alternate local network range
        '#^exp://.*#',              // Expo Go app
        '#^http://localhost.*#',    // Expo web / Metro
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
