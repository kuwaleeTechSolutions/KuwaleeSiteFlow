<?php

return [
    /*
     * Allowlisted MIME types for general document uploads (contracts,
     * drawings, certificates, etc.) — validated against the server-sniffed
     * MIME type, never the client-supplied extension/Content-Type. Photos
     * attached to Daily Reports use the separate, image-only allowlist in
     * config/daily_reports.php.
     */
    'allowed_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/png', 'image/heic', 'image/webp',
    ],

    'max_size_kb' => env('DOCUMENT_MAX_SIZE_KB', 20480), // 20 MB

    /*
     * Expiry alert thresholds, in descending order of days-remaining. The
     * final entry (0) represents "already expired". Configurable per brief
     * §24: "60/30/15/7 days, Expired".
     */
    'expiry_alert_thresholds' => [60, 30, 15, 7, 0],
];
