<?php

return [
    /*
     * Allowed photo MIME types for daily report uploads. Deliberately an
     * allowlist — anything not listed here is rejected regardless of file
     * extension. Validated against the server-sniffed MIME type (finfo),
     * never the client-supplied Content-Type header or filename extension.
     */
    'allowed_photo_mimes' => ['image/jpeg', 'image/png', 'image/heic', 'image/webp'],

    'max_photo_size_kb' => env('DAILY_REPORT_MAX_PHOTO_SIZE_KB', 10240), // 10 MB

    'max_photos_per_report' => env('DAILY_REPORT_MAX_PHOTOS', 20),
];
