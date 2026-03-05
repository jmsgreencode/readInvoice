<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class MatchResultRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?string $status = null): array
    {
        $sql = 'SELECT mr.*, po.po_number, i.invoice_number
                FROM match_results mr
                INNER JOIN purchase_orders po ON po.id = mr.po_id
                INNER JOIN invoices i ON i.id = mr.invoice_id
                WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND mr.status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY mr.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT mr.*, po.po_number, i.invoice_number
             FROM match_results mr
             INNER JOIN purchase_orders po ON po.id = mr.po_id
             INNER JOIN invoices i ON i.id = mr.invoice_id
             WHERE mr.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByInvoiceId(int $invoiceId): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM match_results WHERE invoice_id = ? ORDER BY id DESC LIMIT 1', [$invoiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO match_results (po_id, grn_id, invoice_id, status, po_amount, grn_amount, invoice_amount, tolerance_pct, discrepancies, matched_by, matched_at, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                $data['po_id'], $data['grn_id'] ?? null, $data['invoice_id'],
                $data['status'], $data['po_amount'], $data['grn_amount'] ?? null,
                $data['invoice_amount'], $data['tolerance_pct'],
                json_encode($data['discrepancies'] ?? []),
                $data['matched_by'] ?? null, $data['notes'] ?? null
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function count(?string $status = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM match_results WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND status = ?'; $params[] = $status; }
        $stmt = $this->db->execute($sql, $params);
        return (int)$stmt->fetch()['total'];
    }
}
