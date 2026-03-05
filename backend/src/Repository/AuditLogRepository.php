<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class AuditLogRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function log(
        string $action,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $detail = null,
        ?string $ipAddress = null,
        ?string $requestId = null
    ): void {
        $this->db->execute(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, detail_json, ip_address, request_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                $entityType,
                $entityId,
                $detail ? json_encode($detail) : null,
                $ipAddress,
                $requestId,
            ]
        );
    }

    public function findRecent(int $limit = 100, ?string $action = null): array
    {
        $sql = 'SELECT al.*, u.username FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id WHERE 1=1';
        $params = [];

        if ($action !== null) {
            $sql .= ' AND al.action = ?';
            $params[] = $action;
        }

        $sql .= ' ORDER BY al.created_at DESC LIMIT ?';
        $params[] = $limit;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }
}
