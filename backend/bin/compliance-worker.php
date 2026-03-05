<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Logging\StructuredLogger;
use App\Repository\ComplianceSettingsRepository;
use App\Repository\ComplianceAlertRepository;
use App\Repository\VendorRepository;
use App\Repository\VendorDocumentRepository;
use App\Repository\BudgetRepository;
use App\Service\ComplianceService;

$dbConfig = require __DIR__ . '/../config/database.php';
$logger = new StructuredLogger('compliance-worker');
$db = new Connection($dbConfig['dsn'], $dbConfig['user'], $dbConfig['pass'], $logger);

$service = new ComplianceService(
    new ComplianceSettingsRepository($db),
    new ComplianceAlertRepository($db),
    new VendorRepository($db),
    new VendorDocumentRepository($db),
    new BudgetRepository($db),
    $logger
);

$pollInterval = (int)(getenv('COMPLIANCE_POLL_INTERVAL') ?: 3600);
$logger->info('Compliance worker started', ['poll_interval' => $pollInterval]);

while (true) {
    try {
        $results = $service->runAllChecks();
        $logger->info('Compliance checks completed', $results);
    } catch (\Throwable $e) {
        $logger->error('Compliance check failed', ['error' => $e->getMessage()]);
    }

    sleep($pollInterval);
}
