#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database\Connection;
use App\Logging\StructuredLogger;
use App\Resilience\CircuitBreaker;
use App\Security\ChecksumService;
use App\Repository\EmailRepository;
use App\Repository\InvoiceRepository;
use App\Repository\VendorRepository;
use App\Repository\AuditLogRepository;
use App\Service\VendorService;
use App\Service\PdfService;
use App\Service\InvoiceExtractionService;
use App\Service\EmailIngestionService;
use GuzzleHttp\Client;

if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
}

$logger = new StructuredLogger('email-worker');
$logger->info('Email worker starting');

$dbConfig = require __DIR__ . '/../config/database.php';
$appConfig = require __DIR__ . '/../config/app.php';
$circuitBreakerConfig = require __DIR__ . '/../config/circuit_breaker.php';

try {
    $db = new Connection($dbConfig['dsn'], $dbConfig['user'], $dbConfig['pass'], $logger);
} catch (\Exception $e) {
    $logger->critical('Database connection failed', ['error' => $e->getMessage()]);
    exit(1);
}

$circuitBreaker = new CircuitBreaker($db, $logger, $circuitBreakerConfig);
$checksumService = new ChecksumService($logger);

$vendorRepo = new VendorRepository($db);
$emailRepo = new EmailRepository($db);
$invoiceRepo = new InvoiceRepository($db);
$auditRepo = new AuditLogRepository($db);

$vendorService = new VendorService($vendorRepo, $logger);
$pdfService = new PdfService($appConfig['upload_dir'], $checksumService, $logger);
$extractionService = new InvoiceExtractionService($logger, $appConfig['tesseract_lang']);
$ingestionService = new EmailIngestionService(
    $emailRepo, $invoiceRepo, $auditRepo,
    $vendorService, $pdfService, $extractionService, $logger
);

// Microsoft Graph API configuration
$graphBaseUrl = getenv('GRAPH_API_URL') ?: 'https://graph.microsoft.com/v1.0';
$graphToken = getenv('GRAPH_API_TOKEN') ?: '';
$pollInterval = (int)(getenv('EMAIL_POLL_INTERVAL') ?: 60);
$mailbox = getenv('EMAIL_MAILBOX') ?: 'me';

$http = new Client([
    'base_uri' => $graphBaseUrl,
    'timeout' => 30,
    'headers' => [
        'Authorization' => "Bearer {$graphToken}",
        'Content-Type' => 'application/json',
    ],
]);

$logger->info('Email worker ready', ['poll_interval' => $pollInterval]);

// Main poll loop
$lastCheckTime = date('Y-m-d\TH:i:s\Z', time() - 3600); // Start from 1 hour ago

while (true) {
    try {
        $response = $circuitBreaker->call('outlook_graph_api', function () use ($http, $mailbox, $lastCheckTime) {
            return $http->get("/{$mailbox}/mailFolders/Inbox/messages", [
                'query' => [
                    '$filter' => "hasAttachments eq true and receivedDateTime ge {$lastCheckTime}",
                    '$select' => 'id,from,subject,bodyPreview,receivedDateTime,hasAttachments',
                    '$top' => 50,
                    '$orderby' => 'receivedDateTime desc',
                ],
            ]);
        });

        $data = json_decode($response->getBody()->getContents(), true);
        $messages = $data['value'] ?? [];

        $logger->info('Polled for new emails', ['count' => count($messages)]);

        foreach ($messages as $message) {
            try {
                // Get attachments
                $attachResponse = $circuitBreaker->call('outlook_graph_api', function () use ($http, $mailbox, $message) {
                    return $http->get("/{$mailbox}/messages/{$message['id']}/attachments", [
                        'query' => ['$filter' => "contentType eq 'application/pdf'"],
                    ]);
                });

                $attachments = json_decode($attachResponse->getBody()->getContents(), true)['value'] ?? [];

                $payload = [
                    'outlook_msg_id' => $message['id'],
                    'from_address' => $message['from']['emailAddress']['address'] ?? '',
                    'from_name' => $message['from']['emailAddress']['name'] ?? null,
                    'subject' => $message['subject'] ?? null,
                    'body' => $message['bodyPreview'] ?? null,
                    'received_at' => $message['receivedDateTime'],
                    'attachments' => array_map(function ($att) {
                        return [
                            'name' => $att['name'] ?? 'attachment.pdf',
                            'content_type' => $att['contentType'] ?? 'application/pdf',
                            'content_base64' => $att['contentBytes'] ?? '',
                        ];
                    }, $attachments),
                ];

                $result = $ingestionService->ingest($payload);
                $logger->info('Email processed by worker', [
                    'outlook_msg_id' => $message['id'],
                    'result' => $result['status'],
                ]);
            } catch (\Exception $e) {
                $logger->error('Failed to process email', [
                    'outlook_msg_id' => $message['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $lastCheckTime = date('Y-m-d\TH:i:s\Z');
    } catch (\App\Exception\CircuitOpenException $e) {
        $logger->warning('Circuit breaker open for Graph API, waiting', [
            'wait_seconds' => $pollInterval * 2,
        ]);
        sleep($pollInterval * 2);
        continue;
    } catch (\Exception $e) {
        $logger->error('Email poll cycle failed', ['error' => $e->getMessage()]);
    }

    sleep($pollInterval);
}
