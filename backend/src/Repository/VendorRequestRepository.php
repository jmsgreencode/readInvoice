<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class VendorRequestRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?string $status = null, ?int $requestedBy = null): array
    {
        $sql = 'SELECT vr.*, u.username as requestor_username
                FROM vendor_requests vr
                INNER JOIN users u ON u.id = vr.requested_by
                WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND vr.status = ?'; $params[] = $status; }
        if ($requestedBy) { $sql .= ' AND vr.requested_by = ?'; $params[] = $requestedBy; }
        $sql .= ' ORDER BY vr.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT vr.*, u.username as requestor_username
             FROM vendor_requests vr
             INNER JOIN users u ON u.id = vr.requested_by
             WHERE vr.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO vendor_requests (request_number, requested_by, vendor_name, vendor_website, vendor_contact_name, vendor_contact_email, vendor_contact_phone, business_justification, category)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['request_number'], $data['requested_by'], $data['vendor_name'],
                $data['vendor_website'] ?? null, $data['vendor_contact_name'] ?? null,
                $data['vendor_contact_email'] ?? null, $data['vendor_contact_phone'] ?? null,
                $data['business_justification'], $data['category'] ?? null
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['vendor_name', 'vendor_website', 'vendor_contact_name', 'vendor_contact_email', 'vendor_contact_phone', 'business_justification', 'category'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($fields)) return;
        $params[] = $id;
        $this->db->execute('UPDATE vendor_requests SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public function updateStatus(int $id, string $status, ?int $reviewedBy = null, ?string $reviewNotes = null): void
    {
        $sql = 'UPDATE vendor_requests SET status = ?';
        $params = [$status];
        if ($reviewedBy) { $sql .= ', reviewed_by = ?, reviewed_at = NOW()'; $params[] = $reviewedBy; }
        if ($reviewNotes) { $sql .= ', review_notes = ?'; $params[] = $reviewNotes; }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $this->db->execute($sql, $params);
    }

    public function markPromoted(int $id, int $vendorId): void
    {
        $this->db->execute('UPDATE vendor_requests SET promoted_vendor_id = ? WHERE id = ?', [$vendorId, $id]);
    }

    public function generateNumber(): string
    {
        $year = date('Y');
        $stmt = $this->db->execute("SELECT COUNT(*) as cnt FROM vendor_requests WHERE request_number LIKE ?", ["VR-{$year}-%"]);
        $count = (int)$stmt->fetch()['cnt'] + 1;
        return sprintf('VR-%s-%04d', $year, $count);
    }
}
