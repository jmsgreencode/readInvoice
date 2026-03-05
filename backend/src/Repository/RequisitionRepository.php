<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class RequisitionRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?string $status = null, ?int $departmentId = null, ?int $requestedBy = null): array
    {
        $sql = 'SELECT pr.*, d.name as department_name, u.username as requestor_username, v.name as vendor_name
                FROM purchase_requisitions pr
                INNER JOIN departments d ON d.id = pr.department_id
                INNER JOIN users u ON u.id = pr.requested_by
                LEFT JOIN vendors v ON v.id = pr.vendor_id
                WHERE 1=1';
        $params = [];

        if ($status) { $sql .= ' AND pr.status = ?'; $params[] = $status; }
        if ($departmentId) { $sql .= ' AND pr.department_id = ?'; $params[] = $departmentId; }
        if ($requestedBy) { $sql .= ' AND pr.requested_by = ?'; $params[] = $requestedBy; }

        $sql .= ' ORDER BY pr.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT pr.*, d.name as department_name, u.username as requestor_username, v.name as vendor_name
             FROM purchase_requisitions pr
             INNER JOIN departments d ON d.id = pr.department_id
             INNER JOIN users u ON u.id = pr.requested_by
             LEFT JOIN vendors v ON v.id = pr.vendor_id
             WHERE pr.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO purchase_requisitions (requisition_number, department_id, requested_by, vendor_id, justification, total_amount, currency, budget_id, priority)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['requisition_number'], $data['department_id'], $data['requested_by'],
                $data['vendor_id'] ?? null, $data['justification'] ?? null,
                $data['total_amount'], $data['currency'] ?? 'USD',
                $data['budget_id'] ?? null, $data['priority'] ?? 'medium'
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy = null, ?string $rejectedReason = null): void
    {
        $sql = 'UPDATE purchase_requisitions SET status = ?';
        $params = [$status];

        if ($status === 'approved' && $approvedBy) {
            $sql .= ', approved_by = ?, approved_at = NOW()';
            $params[] = $approvedBy;
        }
        if ($status === 'rejected' && $rejectedReason) {
            $sql .= ', rejected_reason = ?';
            $params[] = $rejectedReason;
        }

        $sql .= ' WHERE id = ?';
        $params[] = $id;

        $this->db->execute($sql, $params);
    }

    public function getLineItems(int $requisitionId): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM requisition_line_items WHERE requisition_id = ? ORDER BY id',
            [$requisitionId]
        );
        return $stmt->fetchAll();
    }

    public function addLineItem(int $requisitionId, string $description, float $quantity, float $unitPrice): int
    {
        $totalPrice = $quantity * $unitPrice;
        $this->db->execute(
            'INSERT INTO requisition_line_items (requisition_id, description, quantity, unit_price, total_price)
             VALUES (?, ?, ?, ?, ?)',
            [$requisitionId, $description, $quantity, $unitPrice, $totalPrice]
        );
        return (int)$this->db->lastInsertId();
    }

    public function recalculateTotal(int $requisitionId): void
    {
        $this->db->execute(
            'UPDATE purchase_requisitions SET total_amount = (
                SELECT COALESCE(SUM(total_price), 0) FROM requisition_line_items WHERE requisition_id = ?
            ) WHERE id = ?',
            [$requisitionId, $requisitionId]
        );
    }

    public function generateNumber(): string
    {
        $year = date('Y');
        $stmt = $this->db->execute(
            "SELECT COUNT(*) as cnt FROM purchase_requisitions WHERE requisition_number LIKE ?",
            ["REQ-{$year}-%"]
        );
        $count = (int)$stmt->fetch()['cnt'] + 1;
        return sprintf('REQ-%s-%04d', $year, $count);
    }

    public function count(?string $status = null, ?int $departmentId = null, ?int $requestedBy = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM purchase_requisitions WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND status = ?'; $params[] = $status; }
        if ($departmentId) { $sql .= ' AND department_id = ?'; $params[] = $departmentId; }
        if ($requestedBy) { $sql .= ' AND requested_by = ?'; $params[] = $requestedBy; }

        $stmt = $this->db->execute($sql, $params);
        return (int)$stmt->fetch()['total'];
    }
}
