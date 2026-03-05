<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Logging\StructuredLogger;
use App\Repository\EmailNotificationRepository;
use App\Service\EmailNotificationService;

$dbConfig = require __DIR__ . '/../config/database.php';
$logger = new StructuredLogger('email-queue-worker');
$db = new Connection($dbConfig['dsn'], $dbConfig['user'], $dbConfig['pass'], $logger);

$service = new EmailNotificationService(new EmailNotificationRepository($db), $logger);

$pollInterval = (int)(getenv('EMAIL_QUEUE_POLL_INTERVAL') ?: 30);
$batchSize = (int)(getenv('EMAIL_QUEUE_BATCH_SIZE') ?: 50);
$logger->info('Email queue worker started', ['poll_interval' => $pollInterval, 'batch_size' => $batchSize]);

while (true) {
    try {
        $sent = $service->processQueue($batchSize);
        if ($sent > 0) {
            $logger->info('Emails sent', ['count' => $sent]);
        }
    } catch (\Throwable $e) {
        $logger->error('Email queue processing failed', ['error' => $e->getMessage()]);
    }

    sleep($pollInterval);
}
