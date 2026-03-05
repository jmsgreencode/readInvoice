<?php

declare(strict_types=1);

return [
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'mysql-router',
        getenv('DB_PORT') ?: '6446',
        getenv('DB_NAME') ?: 'procom'
    ),
    'user' => getenv('DB_USER') ?: 'procom',
    'pass' => getenv('DB_PASS') ?: '',

    // Read-only connection via MySQL Router secondary port
    'dsn_readonly' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'mysql-router',
        getenv('DB_PORT_RO') ?: '6447',
        getenv('DB_NAME') ?: 'procom'
    ),
];
