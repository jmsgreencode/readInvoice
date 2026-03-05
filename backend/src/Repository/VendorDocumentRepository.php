<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class VendorDocumentRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findByVendor(int $vendorId): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM vendor_documents WHERE vendor_id = ? ORDER BY created_at DESC',
            [$vendorId]
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM vendor_documents WHERE id = ?', [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO vendor_documents (vendor_id, document_type, file_name, file_path, file_size, mime_type, uploaded_by, expiry_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['vendor_id'], $data['document_type'], $data['file_name'], $data['file_path'],
                $data['file_size'] ?? null, $data['mime_type'] ?? null, $data['uploaded_by'],
                $data['expiry_date'] ?? null
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM vendor_documents WHERE id = ?', [$id]);
    }

    public function findExpiring(int $withinDays): array
    {
        $stmt = $this->db->execute(
            'SELECT vd.*, v.name as vendor_name FROM vendor_documents vd
             INNER JOIN vendors v ON v.id = vd.vendor_id
             WHERE vd.expiry_date IS NOT NULL
             AND vd.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY vd.expiry_date ASC',
            [$withinDays]
        );
        return $stmt->fetchAll();
    }
}
