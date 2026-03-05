<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class RoleRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $stmt = $this->db->execute('SELECT * FROM roles ORDER BY name');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM roles WHERE id = ?', [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM roles WHERE name = ?', [$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, string $displayName, ?string $description = null): int
    {
        $this->db->execute(
            'INSERT INTO roles (name, display_name, description) VALUES (?, ?, ?)',
            [$name, $displayName, $description]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $displayName, ?string $description = null): void
    {
        $this->db->execute(
            'UPDATE roles SET name = ?, display_name = ?, description = ? WHERE id = ? AND is_system = 0',
            [$name, $displayName, $description, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM roles WHERE id = ? AND is_system = 0', [$id]);
    }

    public function getPermissions(int $roleId): array
    {
        $stmt = $this->db->execute(
            'SELECT p.* FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?
             ORDER BY p.module, p.name',
            [$roleId]
        );
        return $stmt->fetchAll();
    }

    public function setPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->execute('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
        foreach ($permissionIds as $permId) {
            $this->db->execute(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$roleId, (int)$permId]
            );
        }
    }
}
