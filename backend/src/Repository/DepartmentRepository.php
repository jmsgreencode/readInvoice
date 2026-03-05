<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class DepartmentRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $stmt = $this->db->execute(
            'SELECT d.*, u.username as manager_username, u.display_name as manager_name
             FROM departments d
             LEFT JOIN users u ON u.id = d.manager_user_id
             ORDER BY d.name'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT d.*, u.username as manager_username, u.display_name as manager_name
             FROM departments d
             LEFT JOIN users u ON u.id = d.manager_user_id
             WHERE d.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, string $code, ?int $managerUserId = null): int
    {
        $this->db->execute(
            'INSERT INTO departments (name, code, manager_user_id) VALUES (?, ?, ?)',
            [$name, $code, $managerUserId]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $code, ?int $managerUserId = null, bool $isActive = true): void
    {
        $this->db->execute(
            'UPDATE departments SET name = ?, code = ?, manager_user_id = ?, is_active = ? WHERE id = ?',
            [$name, $code, $managerUserId, (int)$isActive, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM departments WHERE id = ?', [$id]);
    }
}
