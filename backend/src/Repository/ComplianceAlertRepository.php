<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class ComplianceAlertRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?string $type = null, ?bool $resolved = null): array
    {
        $sql = 'SELECT * FROM compliance_alerts WHERE 1=1';
        $params = [];
        if ($type) { $sql .= ' AND alert_type = ?'; $params[] = $type; }
        if ($resolved !== null) { $sql .= ' AND is_resolved = ?'; $params[] = (int)$resolved; }
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM compliance_alerts WHERE id = ?', [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $type, string $severity, string $entityType, int $entityId, string $title, string $message): int
    {
        $this->db->execute(
            'INSERT INTO compliance_alerts (alert_type, severity, entity_type, entity_id, title, message) VALUES (?, ?, ?, ?, ?, ?)',
            [$type, $severity, $entityType, $entityId, $title, $message]
        );
        return (int)$this->db->lastInsertId();
    }

    public function resolve(int $id, int $resolvedBy): void
    {
        $this->db->execute(
            'UPDATE compliance_alerts SET is_resolved = 1, resolved_by = ?, resolved_at = NOW() WHERE id = ?',
            [$resolvedBy, $id]
        );
    }

    public function existsUnresolved(string $type, string $entityType, int $entityId): bool
    {
        $stmt = $this->db->execute(
            'SELECT COUNT(*) as cnt FROM compliance_alerts WHERE alert_type = ? AND entity_type = ? AND entity_id = ? AND is_resolved = 0',
            [$type, $entityType, $entityId]
        );
        return (int)$stmt->fetch()['cnt'] > 0;
    }

    public function count(?string $type = null, ?bool $resolved = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM compliance_alerts WHERE 1=1';
        $params = [];
        if ($type) { $sql .= ' AND alert_type = ?'; $params[] = $type; }
        if ($resolved !== null) { $sql .= ' AND is_resolved = ?'; $params[] = (int)$resolved; }
        $stmt = $this->db->execute($sql, $params);
        return (int)$stmt->fetch()['total'];
    }
}
