#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database\Connection;
use App\Database\MigrationRunner;
use App\Logging\StructuredLogger;

if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
}

$dbConfig = require __DIR__ . '/../config/database.php';

$logger = new StructuredLogger('migration');

try {
    $db = new Connection($dbConfig['dsn'], $dbConfig['user'], $dbConfig['pass'], $logger);
    $runner = new MigrationRunner($db, $logger, __DIR__ . '/../src/Database/migrations');
    $runner->run();

    echo "Migrations completed successfully.\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
