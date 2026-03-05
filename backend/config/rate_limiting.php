<?php

declare(strict_types=1);

return [
    'default' => [
        'max_requests' => (int)(getenv('RATE_LIMIT_DEFAULT') ?: 100),
        'window_seconds' => 60,
    ],
    'routes' => [
        '/api/emails/ingest' => [
            'max_requests' => 30,
            'window_seconds' => 60,
        ],
        '/api/auth/login' => [
            'max_requests' => 5,
            'window_seconds' => 300,
        ],
        '/api/auth/refresh' => [
            'max_requests' => 10,
            'window_seconds' => 60,
        ],
        '/api/vsphere/*' => [
            'max_requests' => 20,
            'window_seconds' => 60,
        ],
    ],
];
