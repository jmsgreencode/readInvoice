<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'ReadInvoice',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
        'timezone' => 'UTC',
    ],

    'backend' => [
        'url' => getenv('BACKEND_URL') ?: ($_ENV['BACKEND_URL'] ?? 'http://backend:8080'),
        'timeout' => (int) ($_ENV['BACKEND_TIMEOUT'] ?? 30),
        'connect_timeout' => (int) ($_ENV['BACKEND_CONNECT_TIMEOUT'] ?? 5),
    ],

    'session' => [
        'name' => 'READINVOICE_SESSID',
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => filter_var(getenv('SESSION_SECURE') ?: ($_ENV['SESSION_SECURE'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Strict',
    ],

    'logging' => [
        'channel' => 'frontend',
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'path' => $_ENV['LOG_PATH'] ?? '/var/www/html/var/log/app.log',
    ],

    'rate_limit' => [
        'max_requests' => (int) ($_ENV['RATE_LIMIT_MAX'] ?? 60),
        'window_seconds' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
    ],

    'circuit_breaker' => [
        'failure_threshold' => (int) ($_ENV['CB_FAILURE_THRESHOLD'] ?? 5),
        'recovery_timeout' => (int) ($_ENV['CB_RECOVERY_TIMEOUT'] ?? 30),
    ],
];
