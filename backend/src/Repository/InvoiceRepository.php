<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class InvoiceRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(
        int $offset = 0,
        int $limit = 25,
        ?int $vendorId = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $sql = 'SELECT i.*, v.name as vendor_name, e.subject as email_subject
                FROM invoices i
                LEFT JOIN vendors v ON i.vendor_id = v.id
                LEFT JOIN emails e ON i.email_id = e.id
                WHERE 1=1';
        $params = [];

        if ($vendorId !== null) {
            $sql .= ' AND i.vendor_id = ?';
            $params[] = $vendorId;
        }

        if ($status !== null) {
            $sql .= ' AND i.extraction_status = ?';
            $params[] = $status;
        }

        if ($dateFrom !== null) {
            $sql .= ' AND i.invoice_date >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== null) {
            $sql .= ' AND i.invoice_date <= ?';
            $params[] = $dateTo;
        }

        $sql .= ' ORDER BY i.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT i.*, v.name as vendor_name, e.subject as email_subject, e.from_address as email_from
             FROM invoices i
             LEFT JOIN vendors v ON i.vendor_id = v.id
             LEFT JOIN emails e ON i.email_id = e.id
             WHERE i.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO invoices (email_id, vendor_id, invoice_number, invoice_date, due_date,
             total_amount, currency, pdf_path, pdf_sha256, pdf_original_name, ocr_raw_text,
             extraction_confidence, extraction_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['email_id'],
                $data['vendor_id'] ?? null,
                $data['invoice_number'] ?? null,
                $data['invoice_date'] ?? null,
                $data['due_date'] ?? null,
                $data['total_amount'] ?? null,
                $data['currency'] ?? 'USD',
                $data['pdf_path'],
                $data['pdf_sha256'],
                $data['pdf_original_name'] ?? null,
                $data['ocr_raw_text'] ?? null,
                $data['extraction_confidence'] ?? null,
                $data['extraction_status'] ?? 'pending',
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        $allowedFields = [
            'invoice_number', 'invoice_date', 'due_date', 'total_amount',
            'currency', 'ocr_raw_text', 'extraction_confidence', 'extraction_status',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $params[] = $id;
        $this->db->execute(
            'UPDATE invoices SET ' . implode(', ', $fields) . ' WHERE id = ?',
            $params
        );
    }

    public function findByVendor(int $vendorId, int $offset = 0, int $limit = 25): array
    {
        $stmt = $this->db->execute(
            'SELECT i.*, e.subject as email_subject FROM invoices i
             LEFT JOIN emails e ON i.email_id = e.id
             WHERE i.vendor_id = ? ORDER BY i.created_at DESC LIMIT ? OFFSET ?',
            [$vendorId, $limit, $offset]
        );
        return $stmt->fetchAll();
    }

    public function count(?int $vendorId = null, ?string $status = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM invoices WHERE 1=1';
        $params = [];

        if ($vendorId !== null) {
            $sql .= ' AND vendor_id = ?';
            $params[] = $vendorId;
        }

        if ($status !== null) {
            $sql .= ' AND extraction_status = ?';
            $params[] = $status;
        }

        $stmt = $this->db->execute($sql, $params);
        return (int)$stmt->fetch()['total'];
    }
}
