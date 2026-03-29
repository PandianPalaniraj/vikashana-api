<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        // Local development
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173',   // Vite default (school admin)
        'http://localhost:5174',   // Superadmin portal
        'http://localhost:5175',   // Marketing website
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        // Production
        'https://vikashana.com',
        'https://app.vikashana.com',
        'https://superadmin.vikashana.com',
    ],
    'allowed_origins_patterns' => [
        'http://192\.168\..*',   // local network (phone on WiFi)
        'http://10\..*',         // alternate local network range
        'http://172\..*',        // alternate local network range
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
