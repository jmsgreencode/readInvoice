<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class FileImportRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25): array
    {
        $stmt = $this->db->execute(
            'SELECT fi.*, u.username as imported_by_username
             FROM file_imports fi
             INNER JOIN users u ON u.id = fi.imported_by
             ORDER BY fi.created_at DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM file_imports WHERE id = ?', [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByBatchId(string $batchId): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM file_imports WHERE batch_id = ?', [$batchId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO file_imports (batch_id, file_name, file_type, import_type, imported_by) VALUES (?, ?, ?, ?, ?)',
            [$data['batch_id'], $data['file_name'], $data['file_type'], $data['import_type'], $data['imported_by']]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateProgress(int $id, string $status, int $totalRows, int $processedRows, int $errorRows, ?array $errorLog = null): void
    {
        $sql = 'UPDATE file_imports SET status = ?, total_rows = ?, processed_rows = ?, error_rows = ?, error_log = ?';
        $params = [$status, $totalRows, $processedRows, $errorRows, $errorLog ? json_encode($errorLog) : null];

        if ($status === 'processing') {
            $sql .= ', started_at = COALESCE(started_at, NOW())';
        }
        if (in_array($status, ['completed', 'failed', 'partial'])) {
            $sql .= ', completed_at = NOW()';
        }

        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $this->db->execute($sql, $params);
    }
}
