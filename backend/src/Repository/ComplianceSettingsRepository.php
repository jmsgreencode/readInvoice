<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class ComplianceSettingsRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $stmt = $this->db->execute('SELECT * FROM compliance_settings ORDER BY setting_key');
        return $stmt->fetchAll();
    }

    public function get(string $key): ?string
    {
        $stmt = $this->db->execute('SELECT setting_value FROM compliance_settings WHERE setting_key = ?', [$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : null;
    }

    public function set(string $key, string $value, ?int $updatedBy = null): void
    {
        $this->db->execute(
            'UPDATE compliance_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?',
            [$value, $updatedBy, $key]
        );
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->get($key);
        return $val !== null ? (float)$val : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->get($key);
        if ($val === null) return $default;
        return in_array(strtolower($val), ['true', '1', 'yes'], true);
    }
}
