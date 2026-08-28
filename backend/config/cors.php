<?php

return [

    // Sanctum's /sanctum/csrf-cookie route is NOT under /api, so it must be
    // listed here explicitly. This file must be valid PHP with no stray
    // HTML — that was the actual parse-breaking bug in your previous copy.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // Local development origins (Vite dev server + Laravel dev server on
    // different ports = different origins). Add your production frontend
    // domain here as well once deployed.
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        // 'https://app.kuwaleesiteflow.com', // production frontend
    ],

    'allowed_origins_patterns' => [
        '#^http://localhost(:\d+)?$#',
        '#^http://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // REQUIRED for cookie-based Sanctum SPA auth to work cross-origin.
    'supports_credentials' => true,

];
