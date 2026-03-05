<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class UserRoleRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function getUserRoles(int $userId): array
    {
        $stmt = $this->db->execute(
            'SELECT r.* FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ?
             ORDER BY r.name',
            [$userId]
        );
        return $stmt->fetchAll();
    }

    public function getUserPermissions(int $userId): array
    {
        $stmt = $this->db->execute(
            'SELECT DISTINCT p.name FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?
             ORDER BY p.name',
            [$userId]
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function assignRole(int $userId, int $roleId, ?int $assignedBy = null): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)',
            [$userId, $roleId, $assignedBy]
        );
    }

    public function removeRole(int $userId, int $roleId): void
    {
        $this->db->execute(
            'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?',
            [$userId, $roleId]
        );
    }

    public function getSodConflicts(int $roleId): array
    {
        $stmt = $this->db->execute(
            'SELECT s.*, ra.name as role_a_name, rb.name as role_b_name
             FROM sod_conflict_rules s
             INNER JOIN roles ra ON ra.id = s.role_a_id
             INNER JOIN roles rb ON rb.id = s.role_b_id
             WHERE s.is_active = 1 AND (s.role_a_id = ? OR s.role_b_id = ?)',
            [$roleId, $roleId]
        );
        return $stmt->fetchAll();
    }

    public function findAllUsers(int $offset = 0, int $limit = 25, ?string $search = null): array
    {
        $sql = 'SELECT u.id, u.username, u.display_name, u.email, u.role, u.is_active, u.department_id, u.last_login_at, u.created_at,
                d.name as department_name
                FROM users u
                LEFT JOIN departments d ON d.id = u.department_id';
        $params = [];

        if ($search) {
            $sql .= ' WHERE u.username LIKE ? OR u.display_name LIKE ? OR u.email LIKE ?';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= ' ORDER BY u.username ASC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function countUsers(?string $search = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM users';
        $params = [];

        if ($search) {
            $sql .= ' WHERE username LIKE ? OR display_name LIKE ? OR email LIKE ?';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $stmt = $this->db->execute($sql, $params);
        return (int)$stmt->fetch()['total'];
    }

    public function findUserById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT u.id, u.username, u.display_name, u.email, u.role, u.is_active, u.department_id, u.last_login_at, u.created_at
             FROM users u WHERE u.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateUser(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        foreach (['display_name', 'email', 'department_id', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $params[] = $id;
        $this->db->execute(
            'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?',
            $params
        );
    }
}
