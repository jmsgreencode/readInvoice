<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\FileImportRepository;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class FileImportService
{
    private FileImportRepository $importRepo;
    private CsvImportService $csvService;
    private VendorStagingService $stagingService;
    private StructuredLogger $logger;

    public function __construct(FileImportRepository $importRepo, CsvImportService $csvService, VendorStagingService $stagingService, StructuredLogger $logger)
    {
        $this->importRepo = $importRepo;
        $this->csvService = $csvService;
        $this->stagingService = $stagingService;
        $this->logger = $logger;
    }

    public function list(int $offset = 0, int $limit = 25): array
    {
        return $this->importRepo->findAll($offset, $limit);
    }

    public function get(int $id): ?array
    {
        return $this->importRepo->findById($id);
    }

    public function processUpload(string $filePath, string $fileName, string $importType, int $importedBy): int
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            throw new AppException('Unsupported file type', 'VALIDATION', 400, 'Only CSV, XLSX, and XLS files are supported.');
        }

        $batchId = bin2hex(random_bytes(18));

        $importId = $this->importRepo->create([
            'batch_id' => $batchId,
            'file_name' => $fileName,
            'file_type' => $extension,
            'import_type' => $importType,
            'imported_by' => $importedBy,
        ]);

        try {
            $this->importRepo->updateProgress($importId, 'processing', 0, 0, 0);

            $rows = $this->csvService->parseCsv($filePath);
            $totalRows = count($rows);

            if ($importType === 'vendors') {
                $processed = $this->stagingService->importBatch($rows, $batchId, $importedBy);
                $this->importRepo->updateProgress($importId, 'completed', $totalRows, $processed, $totalRows - $processed);
            } else {
                $this->importRepo->updateProgress($importId, 'failed', $totalRows, 0, 0, [['error' => "Unknown import type: {$importType}"]]);
            }
        } catch (\Exception $e) {
            $this->importRepo->updateProgress($importId, 'failed', 0, 0, 0, [['error' => $e->getMessage()]]);
            $this->logger->error('Import failed', ['import_id' => $importId, 'error' => $e->getMessage()]);
        }

        return $importId;
    }

    public function retry(int $id): void
    {
        $import = $this->importRepo->findById($id);
        if (!$import) throw new AppException('Import not found', 'NOT_FOUND', 404, 'Import not found.');
        if ($import['status'] !== 'failed') throw new AppException('Can only retry failed imports', 'INVALID_STATE', 409, 'Import is not in failed state.');

        $this->importRepo->updateProgress($id, 'pending', 0, 0, 0);
    }
}
