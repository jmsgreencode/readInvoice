<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class GoodsReceivedNoteRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?int $poId = null): array
    {
        $sql = 'SELECT g.*, u.username as received_by_username, po.po_number
                FROM goods_received_notes g
                INNER JOIN users u ON u.id = g.received_by
                INNER JOIN purchase_orders po ON po.id = g.po_id
                WHERE 1=1';
        $params = [];

        if ($poId) { $sql .= ' AND g.po_id = ?'; $params[] = $poId; }

        $sql .= ' ORDER BY g.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT g.*, u.username as received_by_username, po.po_number
             FROM goods_received_notes g
             INNER JOIN users u ON u.id = g.received_by
             INNER JOIN purchase_orders po ON po.id = g.po_id
             WHERE g.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByPoId(int $poId): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM goods_received_notes WHERE po_id = ? ORDER BY created_at DESC',
            [$poId]
        );
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO goods_received_notes (grn_number, po_id, received_by, received_date, notes) VALUES (?, ?, ?, ?, ?)',
            [$data['grn_number'], $data['po_id'], $data['received_by'], $data['received_date'], $data['notes'] ?? null]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->execute('UPDATE goods_received_notes SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function getLineItems(int $grnId): array
    {
        $stmt = $this->db->execute(
            'SELECT gli.*, pli.description, pli.quantity as ordered_qty
             FROM grn_line_items gli
             INNER JOIN po_line_items pli ON pli.id = gli.po_line_item_id
             WHERE gli.grn_id = ? ORDER BY gli.id',
            [$grnId]
        );
        return $stmt->fetchAll();
    }

    public function addLineItem(int $grnId, int $poLineItemId, float $receivedQty, float $acceptedQty, float $rejectedQty = 0, ?string $rejectionReason = null): int
    {
        $this->db->execute(
            'INSERT INTO grn_line_items (grn_id, po_line_item_id, received_qty, accepted_qty, rejected_qty, rejection_reason)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$grnId, $poLineItemId, $receivedQty, $acceptedQty, $rejectedQty, $rejectionReason]
        );
        return (int)$this->db->lastInsertId();
    }

    public function generateNumber(): string
    {
        $year = date('Y');
        $stmt = $this->db->execute("SELECT COUNT(*) as cnt FROM goods_received_notes WHERE grn_number LIKE ?", ["GRN-{$year}-%"]);
        $count = (int)$stmt->fetch()['cnt'] + 1;
        return sprintf('GRN-%s-%04d', $year, $count);
    }

    public function getTotalReceivedForPo(int $poId): float
    {
        $stmt = $this->db->execute(
            'SELECT COALESCE(SUM(gli.accepted_qty * pli.unit_price), 0) as total
             FROM grn_line_items gli
             INNER JOIN goods_received_notes grn ON grn.id = gli.grn_id
             INNER JOIN po_line_items pli ON pli.id = gli.po_line_item_id
             WHERE grn.po_id = ? AND grn.status = ?',
            [$poId, 'confirmed']
        );
        return (float)$stmt->fetch()['total'];
    }
}
