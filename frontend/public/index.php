<?php

declare(strict_types=1);

use ReadInvoice\Frontend\Kernel;

// Prevent direct access to this file's source
if (php_sapi_name() === 'cli') {
    return;
}

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Bootstrap and run
$kernel = new Kernel();
$app = $kernel->bootstrap();
$app->run();
