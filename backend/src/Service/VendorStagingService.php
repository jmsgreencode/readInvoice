<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VendorStagingRepository;
use App\Repository\VendorRepository;
use App\Database\Connection;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class VendorStagingService
{
    private VendorStagingRepository $stagingRepo;
    private VendorRepository $vendorRepo;
    private Connection $db;
    private StructuredLogger $logger;

    public function __construct(VendorStagingRepository $stagingRepo, VendorRepository $vendorRepo, Connection $db, StructuredLogger $logger)
    {
        $this->stagingRepo = $stagingRepo;
        $this->vendorRepo = $vendorRepo;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function list(int $offset = 0, int $limit = 25, ?string $batchId = null, ?string $status = null): array
    {
        return $this->stagingRepo->findAll($offset, $limit, $batchId, $status);
    }

    public function get(int $id): ?array
    {
        return $this->stagingRepo->findById($id);
    }

    public function importBatch(array $rows, string $batchId, int $importedBy): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            if (empty($name)) {
                $this->stagingRepo->create([
                    'batch_id' => $batchId,
                    'name' => $name ?: '(empty)',
                    'domain' => $row['domain'] ?? null,
                    'contact_email' => $row['contact_email'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'raw_data' => $row,
                    'validation_status' => 'invalid',
                    'validation_errors' => 'Name is required',
                    'imported_by' => $importedBy,
                ]);
                continue;
            }

            // Check for duplicates
            $existing = $this->vendorRepo->findByName($name);
            $errors = [];
            if ($existing) {
                $errors[] = "Vendor with name '{$name}' already exists (ID: {$existing['id']})";
            }

            $this->stagingRepo->create([
                'batch_id' => $batchId,
                'name' => $name,
                'domain' => $row['domain'] ?? null,
                'contact_email' => $row['contact_email'] ?? null,
                'notes' => $row['notes'] ?? null,
                'raw_data' => $row,
                'validation_status' => empty($errors) ? 'valid' : 'invalid',
                'validation_errors' => empty($errors) ? null : implode('; ', $errors),
                'imported_by' => $importedBy,
            ]);
            $count++;
        }
        return $count;
    }

    public function promote(int $stagingId, int $reviewedBy): int
    {
        $staged = $this->stagingRepo->findById($stagingId);
        if (!$staged) throw new AppException('Staged vendor not found', 'NOT_FOUND', 404, 'Staged vendor not found.');
        if ($staged['promoted_vendor_id']) throw new AppException('Already promoted', 'CONFLICT', 409, 'This vendor has already been promoted.');

        $this->db->beginTransaction();
        try {
            $vendorId = $this->vendorRepo->create($staged['name'], $staged['domain'], $staged['contact_email']);
            $this->stagingRepo->markPromoted($stagingId, $vendorId, $reviewedBy);
            $this->db->commit();

            $this->logger->info('Staged vendor promoted', ['staging_id' => $stagingId, 'vendor_id' => $vendorId]);
            return $vendorId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
