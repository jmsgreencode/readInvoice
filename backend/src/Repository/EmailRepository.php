<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class EmailRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?int $vendorId = null, ?string $status = null): array
    {
        $sql = 'SELECT e.*, v.name as vendor_name FROM emails e
                LEFT JOIN vendors v ON e.vendor_id = v.id WHERE 1=1';
        $params = [];

        if ($vendorId !== null) {
            $sql .= ' AND e.vendor_id = ?';
            $params[] = $vendorId;
        }

        if ($status !== null) {
            $sql .= ' AND e.processing_status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY e.received_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT e.*, v.name as vendor_name FROM emails e
             LEFT JOIN vendors v ON e.vendor_id = v.id WHERE e.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByOutlookMsgId(string $msgId): ?array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM emails WHERE outlook_msg_id = ?',
            [$msgId]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO emails (vendor_id, outlook_msg_id, from_address, from_name, subject,
             received_at, body_preview, has_attachments, has_invoice, processing_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['vendor_id'] ?? null,
                $data['outlook_msg_id'],
                $data['from_address'],
                $data['from_name'] ?? null,
                $data['subject'] ?? null,
                $data['received_at'],
                $data['body_preview'] ?? null,
                $data['has_attachments'] ? 1 : 0,
                $data['has_invoice'] ? 1 : 0,
                $data['processing_status'] ?? 'pending',
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $errorMessage = null): void
    {
        $this->db->execute(
            'UPDATE emails SET processing_status = ?, error_message = ? WHERE id = ?',
            [$status, $errorMessage, $id]
        );
    }

    public function findByVendor(int $vendorId, int $offset = 0, int $limit = 25): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM emails WHERE vendor_id = ? ORDER BY received_at DESC LIMIT ? OFFSET ?',
            [$vendorId, $limit, $offset]
        );
        return $stmt->fetchAll();
    }

    public function count(?int $vendorId = null, ?string $status = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM emails WHERE 1=1';
        $params = [];

        if ($vendorId !== null) {
            $sql .= ' AND vendor_id = ?';
            $params[] = $vendorId;
        }

        if ($status !== null) {
            $sql .= ' AND processing_status = ?';
            $params[] = $status;
        }

        $stmt = $this->db->execute($sql, $params);
        return (int)$stmt->fetch()['total'];
    }
}
