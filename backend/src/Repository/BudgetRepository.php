<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class BudgetRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findAll(?int $fiscalYear = null, ?int $departmentId = null): array
    {
        $sql = 'SELECT b.*, d.name as department_name, d.code as department_code
                FROM budgets b
                INNER JOIN departments d ON d.id = b.department_id
                WHERE 1=1';
        $params = [];

        if ($fiscalYear) {
            $sql .= ' AND b.fiscal_year = ?';
            $params[] = $fiscalYear;
        }
        if ($departmentId) {
            $sql .= ' AND b.department_id = ?';
            $params[] = $departmentId;
        }

        $sql .= ' ORDER BY b.fiscal_year DESC, d.name ASC';

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->execute(
            'SELECT b.*, d.name as department_name, d.code as department_code
             FROM budgets b
             INNER JOIN departments d ON d.id = b.department_id
             WHERE b.id = ?',
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByDepartmentAndYear(int $departmentId, int $fiscalYear): ?array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM budgets WHERE department_id = ? AND fiscal_year = ?',
            [$departmentId, $fiscalYear]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $departmentId, int $fiscalYear, float $totalAmount, string $currency = 'USD'): int
    {
        $this->db->execute(
            'INSERT INTO budgets (department_id, fiscal_year, total_amount, currency) VALUES (?, ?, ?, ?)',
            [$departmentId, $fiscalYear, $totalAmount, $currency]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, float $totalAmount): void
    {
        $this->db->execute('UPDATE budgets SET total_amount = ? WHERE id = ?', [$totalAmount, $id]);
    }

    /**
     * Lock row for update and return current values. Must be called within a transaction.
     */
    public function findByIdForUpdate(int $id): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM budgets WHERE id = ? FOR UPDATE', [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allocate(int $id, float $amount): void
    {
        $this->db->execute(
            'UPDATE budgets SET allocated_amount = allocated_amount + ? WHERE id = ?',
            [$amount, $id]
        );
    }

    public function deallocate(int $id, float $amount): void
    {
        $this->db->execute(
            'UPDATE budgets SET allocated_amount = GREATEST(0, allocated_amount - ?) WHERE id = ?',
            [$amount, $id]
        );
    }

    public function spend(int $id, float $amount): void
    {
        $this->db->execute(
            'UPDATE budgets SET spent_amount = spent_amount + ? WHERE id = ?',
            [$amount, $id]
        );
    }

    public function findOverThreshold(float $thresholdPct): array
    {
        $stmt = $this->db->execute(
            'SELECT b.*, d.name as department_name,
             ROUND((b.allocated_amount / b.total_amount) * 100, 2) as utilization_pct
             FROM budgets b
             INNER JOIN departments d ON d.id = b.department_id
             WHERE b.total_amount > 0 AND ((b.allocated_amount / b.total_amount) * 100) >= ?
             ORDER BY utilization_pct DESC',
            [$thresholdPct]
        );
        return $stmt->fetchAll();
    }
}
