<?php

declare(strict_types=1);

namespace App\Security;

use App\Logging\StructuredLogger;

class ChecksumService
{
    private StructuredLogger $logger;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
    }

    public function generateFileChecksum(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $hash = hash_file('sha256', $filePath);
        if ($hash === false) {
            throw new \RuntimeException("Failed to compute SHA-256 for: {$filePath}");
        }

        $this->logger->info('SHA-256 checksum generated', [
            'file' => basename($filePath),
            'sha256' => $hash,
        ]);

        return $hash;
    }

    public function verifyFileChecksum(string $filePath, string $expectedHash): bool
    {
        $actualHash = $this->generateFileChecksum($filePath);
        $valid = hash_equals($expectedHash, $actualHash);

        if (!$valid) {
            $this->logger->critical('SHA-256 checksum mismatch', [
                'file' => basename($filePath),
                'expected' => $expectedHash,
                'actual' => $actualHash,
            ]);
        }

        return $valid;
    }

    public function generateStringChecksum(string $data): string
    {
        return hash('sha256', $data);
    }
}
