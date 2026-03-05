<?php

declare(strict_types=1);

namespace App\Service;

use App\Security\ChecksumService;
use App\Security\InputValidator;
use App\Logging\StructuredLogger;

class PdfService
{
    private string $uploadDir;
    private ChecksumService $checksumService;
    private StructuredLogger $logger;

    public function __construct(string $uploadDir, ChecksumService $checksumService, StructuredLogger $logger)
    {
        $this->uploadDir = $uploadDir;
        $this->checksumService = $checksumService;
        $this->logger = $logger;
    }

    /**
     * Store a PDF from base64-encoded content.
     *
     * @return array{path: string, sha256: string, filename: string}
     */
    public function storeFromBase64(string $base64Content, string $originalName, int $vendorId): array
    {
        $decoded = base64_decode($base64Content, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64 content');
        }

        $this->validatePdfMagicBytes($decoded);

        $safeFilename = InputValidator::sanitizeFilename($originalName);
        $date = date('Y-m-d');
        $directory = "{$this->uploadDir}/{$vendorId}/{$date}";

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $sha256 = hash('sha256', $decoded);
        $storedFilename = "{$sha256}.pdf";
        $fullPath = "{$directory}/{$storedFilename}";

        $written = file_put_contents($fullPath, $decoded);
        if ($written === false) {
            throw new \RuntimeException('Failed to write PDF file');
        }

        // Verify checksum after write
        if (!$this->checksumService->verifyFileChecksum($fullPath, $sha256)) {
            unlink($fullPath);
            throw new \RuntimeException('PDF checksum verification failed after write');
        }

        $this->logger->info('PDF stored successfully', [
            'vendor_id' => $vendorId,
            'original_name' => $safeFilename,
            'sha256' => $sha256,
            'size_bytes' => $written,
        ]);

        $relativePath = "{$vendorId}/{$date}/{$storedFilename}";

        return [
            'path' => $relativePath,
            'sha256' => $sha256,
            'filename' => $safeFilename,
        ];
    }

    /**
     * Store a PDF from an uploaded file.
     *
     * @return array{path: string, sha256: string, filename: string}
     */
    public function storeFromUpload(array $uploadedFile, int $vendorId): array
    {
        InputValidator::validateFileUpload(
            $uploadedFile,
            ['application/pdf'],
            50 * 1024 * 1024 // 50MB max
        );

        $content = file_get_contents($uploadedFile['tmp_name']);
        $this->validatePdfMagicBytes($content);

        $safeFilename = InputValidator::sanitizeFilename($uploadedFile['name']);
        $date = date('Y-m-d');
        $directory = "{$this->uploadDir}/{$vendorId}/{$date}";

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $sha256 = hash('sha256', $content);
        $storedFilename = "{$sha256}.pdf";
        $fullPath = "{$directory}/{$storedFilename}";

        if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
            throw new \RuntimeException('Failed to move uploaded PDF');
        }

        if (!$this->checksumService->verifyFileChecksum($fullPath, $sha256)) {
            unlink($fullPath);
            throw new \RuntimeException('PDF checksum verification failed after write');
        }

        $this->logger->info('PDF uploaded and stored', [
            'vendor_id' => $vendorId,
            'original_name' => $safeFilename,
            'sha256' => $sha256,
        ]);

        $relativePath = "{$vendorId}/{$date}/{$storedFilename}";

        return [
            'path' => $relativePath,
            'sha256' => $sha256,
            'filename' => $safeFilename,
        ];
    }

    public function getFullPath(string $relativePath): string
    {
        $fullPath = $this->uploadDir . '/' . $relativePath;

        // Prevent directory traversal
        $realPath = realpath($fullPath);
        $realUploadDir = realpath($this->uploadDir);

        if ($realPath === false || !str_starts_with($realPath, $realUploadDir)) {
            throw new \RuntimeException('Invalid file path');
        }

        return $realPath;
    }

    private function validatePdfMagicBytes(string $content): void
    {
        if (strlen($content) < 4 || substr($content, 0, 4) !== '%PDF') {
            throw new \RuntimeException('File is not a valid PDF');
        }
    }
}
