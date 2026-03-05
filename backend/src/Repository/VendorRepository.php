<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;
use App\Model\Vendor;

class VendorRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(int $offset = 0, int $limit = 25, ?string $search = null): array
    {
        $sql = 'SELECT v.*,
                (SELECT COUNT(*) FROM emails WHERE vendor_id = v.id) as email_count,
                (SELECT COUNT(*) FROM invoices WHERE vendor_id = v.id) as invoice_count
                FROM vendors v';
        $params = [];

        if ($search) {
            $sql .= ' WHERE v.name LIKE ? OR v.domain LIKE ?';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= ' ORDER BY v.name ASC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT v.*,
             (SELECT COUNT(*) FROM emails WHERE vendor_id = v.id) as email_count,
             (SELECT COUNT(*) FROM invoices WHERE vendor_id = v.id) as invoice_count
             FROM vendors v WHERE v.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM vendors WHERE name = ?', [$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByDomain(string $domain): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM vendors WHERE domain = ?', [$domain]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, ?string $domain = null, ?string $contactEmail = null): int
    {
        $this->db->execute(
            'INSERT INTO vendors (name, domain, contact_email) VALUES (?, ?, ?)',
            [$name, $domain, $contactEmail]
        );
        return (int)$this->db->lastInsertId();
    }

    public function count(?string $search = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM vendors';
        $params = [];

        if ($search) {
            $sql .= ' WHERE name LIKE ? OR domain LIKE ?';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $stmt = $this->db->execute($sql, $params);
        return (int)$stmt->fetch()['total'];
    }

    public function updateLifecycle(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        $allowed = ['effective_date', 'expiry_date', 'verification_status', 'verified_by', 'verified_at', 'risk_rating', 'is_blocked', 'blocked_reason', 'notes'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) return;

        $params[] = $id;
        $this->db->execute('UPDATE vendors SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public function findExpiring(int $withinDays): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM vendors WHERE expiry_date IS NOT NULL AND is_blocked = 0
             AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY expiry_date ASC',
            [$withinDays]
        );
        return $stmt->fetchAll();
    }

    public function findExpired(): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM vendors WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() ORDER BY expiry_date ASC'
        );
        return $stmt->fetchAll();
    }
}
