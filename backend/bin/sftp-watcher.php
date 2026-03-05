<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Logging\StructuredLogger;
use App\Repository\FileImportRepository;
use App\Repository\VendorStagingRepository;
use App\Repository\VendorRepository;
use App\Service\CsvImportService;
use App\Service\VendorStagingService;
use App\Service\FileImportService;

$dbConfig = require __DIR__ . '/../config/database.php';
$logger = new StructuredLogger('sftp-watcher');
$db = new Connection($dbConfig['dsn'], $dbConfig['user'], $dbConfig['pass'], $logger);

$csvService = new CsvImportService($logger);
$vendorRepo = new VendorRepository($db);
$stagingRepo = new VendorStagingRepository($db);
$importRepo = new FileImportRepository($db);
$stagingService = new VendorStagingService($stagingRepo, $vendorRepo, $db, $logger);
$importService = new FileImportService($importRepo, $csvService, $stagingService, $logger);

$watchDir = getenv('SFTP_WATCH_DIR') ?: '/var/sftp/incoming';
$processedDir = getenv('SFTP_PROCESSED_DIR') ?: '/var/sftp/processed';
$pollInterval = (int)(getenv('SFTP_POLL_INTERVAL') ?: 60);
$systemUserId = 1; // System user for auto-imports

$logger->info('SFTP watcher started', ['watch_dir' => $watchDir, 'poll_interval' => $pollInterval]);

if (!is_dir($processedDir)) {
    mkdir($processedDir, 0755, true);
}

while (true) {
    try {
        if (is_dir($watchDir)) {
            $files = glob($watchDir . '/*.{csv,CSV}', GLOB_BRACE);

            foreach ($files as $filePath) {
                $fileName = basename($filePath);
                $logger->info('Processing SFTP file', ['file' => $fileName]);

                try {
                    $importService->processUpload($filePath, $fileName, 'vendors', $systemUserId);

                    // Move to processed directory
                    $destPath = $processedDir . '/' . date('Ymd_His') . '_' . $fileName;
                    rename($filePath, $destPath);

                    $logger->info('SFTP file processed', ['file' => $fileName]);
                } catch (\Throwable $e) {
                    $logger->error('SFTP file processing failed', ['file' => $fileName, 'error' => $e->getMessage()]);
                }
            }
        }
    } catch (\Throwable $e) {
        $logger->error('SFTP watcher error', ['error' => $e->getMessage()]);
    }

    sleep($pollInterval);
}
