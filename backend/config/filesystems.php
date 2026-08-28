<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],

        // Dedicated PRIVATE disk for documents & daily-report photos
        // (Phases 4 & 9). Never mapped to a public URL — files are only
        // ever streamed through a policy-gated controller endpoint.
        'private-documents' => [
            'driver' => env('DOCUMENTS_DISK_DRIVER', 'local'),
            'root' => storage_path('app/private-documents'),
            'visibility' => 'private',
            'throw' => true,
            // When DOCUMENTS_DISK_DRIVER=s3 in production:
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_DOCUMENTS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

        // Public disk reserved ONLY for non-sensitive branding assets
        // (organization logos) — never for documents or field photos.
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
