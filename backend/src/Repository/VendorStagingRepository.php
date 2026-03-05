<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class VendorStagingRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?string $batchId = null, ?string $status = null): array
    {
        $sql = 'SELECT * FROM vendor_staging WHERE 1=1';
        $params = [];
        if ($batchId) { $sql .= ' AND batch_id = ?'; $params[] = $batchId; }
        if ($status) { $sql .= ' AND validation_status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM vendor_staging WHERE id = ?', [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO vendor_staging (batch_id, name, domain, contact_email, notes, raw_data, validation_status, validation_errors, imported_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['batch_id'], $data['name'], $data['domain'] ?? null,
                $data['contact_email'] ?? null, $data['notes'] ?? null,
                json_encode($data['raw_data'] ?? null),
                $data['validation_status'] ?? 'pending',
                $data['validation_errors'] ?? null,
                $data['imported_by']
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function markPromoted(int $id, int $vendorId, int $reviewedBy): void
    {
        $this->db->execute(
            'UPDATE vendor_staging SET promoted_vendor_id = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
            [$vendorId, $reviewedBy, $id]
        );
    }

    public function updateValidation(int $id, string $status, ?string $errors = null): void
    {
        $this->db->execute(
            'UPDATE vendor_staging SET validation_status = ?, validation_errors = ? WHERE id = ?',
            [$status, $errors, $id]
        );
    }
}
