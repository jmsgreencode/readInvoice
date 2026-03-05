<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class PermissionRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $stmt = $this->db->execute('SELECT * FROM permissions ORDER BY module, name');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM permissions WHERE id = ?', [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM permissions WHERE name = ?', [$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByModule(string $module): array
    {
        $stmt = $this->db->execute('SELECT * FROM permissions WHERE module = ? ORDER BY name', [$module]);
        return $stmt->fetchAll();
    }
}
