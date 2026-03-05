<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\BudgetRepository;
use App\Database\Connection;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class BudgetService
{
    private BudgetRepository $repo;
    private Connection $db;
    private StructuredLogger $logger;

    public function __construct(BudgetRepository $repo, Connection $db, StructuredLogger $logger)
    {
        $this->repo = $repo;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function list(?int $fiscalYear = null, ?int $departmentId = null): array
    {
        return $this->repo->findAll($fiscalYear, $departmentId);
    }

    public function get(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function create(int $departmentId, int $fiscalYear, float $totalAmount, string $currency = 'USD'): int
    {
        $existing = $this->repo->findByDepartmentAndYear($departmentId, $fiscalYear);
        if ($existing) {
            throw new AppException('Budget already exists for this department and year', 'CONFLICT', 409, 'Budget already exists.');
        }
        return $this->repo->create($departmentId, $fiscalYear, $totalAmount, $currency);
    }

    public function update(int $id, float $totalAmount): void
    {
        $budget = $this->repo->findById($id);
        if (!$budget) {
            throw new AppException('Budget not found', 'NOT_FOUND', 404, 'Budget not found.');
        }
        $this->repo->update($id, $totalAmount);
    }

    public function getUtilization(int $id): array
    {
        $budget = $this->repo->findById($id);
        if (!$budget) {
            throw new AppException('Budget not found', 'NOT_FOUND', 404, 'Budget not found.');
        }

        $remaining = (float)$budget['total_amount'] - (float)$budget['allocated_amount'];
        $utilizationPct = $budget['total_amount'] > 0
            ? round(((float)$budget['allocated_amount'] / (float)$budget['total_amount']) * 100, 2)
            : 0;

        return [
            'budget' => $budget,
            'remaining' => $remaining,
            'utilization_pct' => $utilizationPct,
        ];
    }

    /**
     * Atomically allocate budget. Uses SELECT FOR UPDATE to prevent race conditions.
     */
    public function allocate(int $budgetId, float $amount): void
    {
        $this->db->beginTransaction();
        try {
            $budget = $this->repo->findByIdForUpdate($budgetId);
            if (!$budget) {
                throw new AppException('Budget not found', 'NOT_FOUND', 404, 'Budget not found.');
            }

            $remaining = (float)$budget['total_amount'] - (float)$budget['allocated_amount'];
            if ($amount > $remaining) {
                throw new AppException(
                    'Insufficient budget',
                    'BUDGET_EXCEEDED',
                    409,
                    sprintf('Requested %.2f but only %.2f remaining.', $amount, $remaining)
                );
            }

            $this->repo->allocate($budgetId, $amount);
            $this->db->commit();

            $this->logger->info('Budget allocated', ['budget_id' => $budgetId, 'amount' => $amount]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deallocate(int $budgetId, float $amount): void
    {
        $this->repo->deallocate($budgetId, $amount);
        $this->logger->info('Budget deallocated', ['budget_id' => $budgetId, 'amount' => $amount]);
    }
}
