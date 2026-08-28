<?php

return [
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
    ],
    'rate_limits' => [
        'login_per_minute' => 5,
        'read_per_minute' => 120,
        'mutation_per_minute' => 60,
        'upload_per_minute' => 20,
        'export_per_minute' => 20,
    ],
];
