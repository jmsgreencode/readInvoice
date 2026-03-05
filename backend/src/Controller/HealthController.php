<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database\Connection;
use App\Logging\StructuredLogger;

class HealthController
{
    private Connection $db;
    private StructuredLogger $logger;
    private string $uploadDir;

    public function __construct(Connection $db, StructuredLogger $logger, string $uploadDir)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->uploadDir = $uploadDir;
    }

    public function health(Request $request, Response $response): Response
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'disk' => $this->checkDisk(),
            'tesseract' => $this->checkTesseract(),
        ];

        $allHealthy = !in_array(false, array_column($checks, 'healthy'), true);

        $payload = [
            'success' => true,
            'data' => [
                'status' => $allHealthy ? 'healthy' : 'degraded',
                'checks' => $checks,
                'uptime' => $this->getUptime(),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
        ];

        $response->getBody()->write(json_encode($payload));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($allHealthy ? 200 : 503);
    }

    public function ready(Request $request, Response $response): Response
    {
        $dbHealthy = $this->checkDatabase()['healthy'];

        $response->getBody()->write(json_encode([
            'ready' => $dbHealthy,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($dbHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            $this->db->execute('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['healthy' => true, 'latency_ms' => $latency];
        } catch (\Exception $e) {
            return ['healthy' => false, 'error' => 'Connection failed'];
        }
    }

    private function checkDisk(): array
    {
        $freeBytes = disk_free_space($this->uploadDir);
        $totalBytes = disk_total_space($this->uploadDir);

        if ($freeBytes === false || $totalBytes === false) {
            return ['healthy' => false, 'error' => 'Cannot read disk info'];
        }

        $freePercent = round(($freeBytes / $totalBytes) * 100, 1);
        return [
            'healthy' => $freePercent > 5.0,
            'free_percent' => $freePercent,
            'free_gb' => round($freeBytes / 1073741824, 2),
        ];
    }

    private function checkTesseract(): array
    {
        exec('tesseract --version 2>&1', $output, $returnCode);
        return [
            'healthy' => $returnCode === 0,
            'version' => $output[0] ?? 'unknown',
        ];
    }

    private function getUptime(): string
    {
        if (function_exists('getrusage')) {
            $usage = getrusage();
            $seconds = $usage['ru_utime.tv_sec'] ?? 0;
            return gmdate('H:i:s', $seconds);
        }
        return 'unknown';
    }
}
