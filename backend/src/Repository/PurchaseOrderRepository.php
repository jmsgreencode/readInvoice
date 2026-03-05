<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class PurchaseOrderRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?string $status = null, ?int $vendorId = null): array
    {
        $sql = 'SELECT po.*, v.name as vendor_name, u.username as created_by_username
                FROM purchase_orders po
                INNER JOIN vendors v ON v.id = po.vendor_id
                INNER JOIN users u ON u.id = po.created_by
                WHERE 1=1';
        $params = [];

        if ($status) { $sql .= ' AND po.status = ?'; $params[] = $status; }
        if ($vendorId) { $sql .= ' AND po.vendor_id = ?'; $params[] = $vendorId; }

        $sql .= ' ORDER BY po.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT po.*, v.name as vendor_name, u.username as created_by_username
             FROM purchase_orders po
             INNER JOIN vendors v ON v.id = po.vendor_id
             INNER JOIN users u ON u.id = po.created_by
             WHERE po.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO purchase_orders (po_number, requisition_id, vendor_id, created_by, total_amount, currency, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['po_number'], $data['requisition_id'] ?? null, $data['vendor_id'],
                $data['created_by'], $data['total_amount'], $data['currency'] ?? 'USD',
                $data['notes'] ?? null
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $sql = 'UPDATE purchase_orders SET status = ?';
        $params = [$status];
        if ($status === 'issued') {
            $sql .= ', issued_at = NOW()';
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $this->db->execute($sql, $params);
    }

    public function getLineItems(int $poId): array
    {
        $stmt = $this->db->execute('SELECT * FROM po_line_items WHERE po_id = ? ORDER BY id', [$poId]);
        return $stmt->fetchAll();
    }

    public function addLineItem(int $poId, string $description, float $quantity, float $unitPrice): int
    {
        $totalPrice = $quantity * $unitPrice;
        $this->db->execute(
            'INSERT INTO po_line_items (po_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)',
            [$poId, $description, $quantity, $unitPrice, $totalPrice]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateLineItemReceivedQty(int $lineItemId, float $receivedQty): void
    {
        $this->db->execute(
            'UPDATE po_line_items SET received_qty = ? WHERE id = ?',
            [$receivedQty, $lineItemId]
        );
    }

    public function recalculateTotal(int $poId): void
    {
        $this->db->execute(
            'UPDATE purchase_orders SET total_amount = (
                SELECT COALESCE(SUM(total_price), 0) FROM po_line_items WHERE po_id = ?
            ) WHERE id = ?',
            [$poId, $poId]
        );
    }

    public function generateNumber(): string
    {
        $year = date('Y');
        $stmt = $this->db->execute(
            "SELECT COUNT(*) as cnt FROM purchase_orders WHERE po_number LIKE ?",
            ["PO-{$year}-%"]
        );
        $count = (int)$stmt->fetch()['cnt'] + 1;
        return sprintf('PO-%s-%04d', $year, $count);
    }

    public function count(?string $status = null, ?int $vendorId = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM purchase_orders WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND status = ?'; $params[] = $status; }
        if ($vendorId) { $sql .= ' AND vendor_id = ?'; $params[] = $vendorId; }
        $stmt = $this->db->execute($sql, $params);
        return (int)$stmt->fetch()['total'];
    }
}
