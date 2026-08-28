<?php

use Laravel\Sanctum\Sanctum;

return [
    // Frontend origins allowed to authenticate against this API via the
    // encrypted, HttpOnly session cookie. Requests from any other origin
    // are treated as third-party API clients (token-based, not cookie-based).
    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1'
    )),

    'guard' => ['web'],

    // Bearer tokens (personal_access_tokens) expire by default after 30
    // days if ever used for a future mobile-app integration; the SPA cookie
    // session itself is governed by SESSION_LIFETIME instead.
    'expiration' => 60 * 24 * 30,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'verify_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
    ],
];
