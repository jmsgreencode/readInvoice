<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\RequisitionRepository;
use App\Database\Connection;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class RequisitionService
{
    private RequisitionRepository $repo;
    private BudgetService $budgetService;
    private Connection $db;
    private StructuredLogger $logger;

    public function __construct(RequisitionRepository $repo, BudgetService $budgetService, Connection $db, StructuredLogger $logger)
    {
        $this->repo = $repo;
        $this->budgetService = $budgetService;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function list(int $offset = 0, int $limit = 25, ?string $status = null, ?int $departmentId = null, ?int $requestedBy = null): array
    {
        return $this->repo->findAll($offset, $limit, $status, $departmentId, $requestedBy);
    }

    public function get(int $id): ?array
    {
        $req = $this->repo->findById($id);
        if ($req) {
            $req['line_items'] = $this->repo->getLineItems($id);
        }
        return $req;
    }

    public function create(array $data, array $lineItems): int
    {
        $this->db->beginTransaction();
        try {
            $data['requisition_number'] = $this->repo->generateNumber();
            $data['total_amount'] = 0;
            $reqId = $this->repo->create($data);

            foreach ($lineItems as $item) {
                $this->repo->addLineItem($reqId, $item['description'], (float)$item['quantity'], (float)$item['unit_price']);
            }

            $this->repo->recalculateTotal($reqId);
            $this->db->commit();

            $this->logger->info('Requisition created', ['id' => $reqId, 'number' => $data['requisition_number']]);
            return $reqId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function submit(int $id): void
    {
        $req = $this->repo->findById($id);
        if (!$req) throw new AppException('Requisition not found', 'NOT_FOUND', 404, 'Requisition not found.');
        if ($req['status'] !== 'draft') throw new AppException('Can only submit draft requisitions', 'INVALID_STATE', 409, 'Requisition is not in draft state.');

        $this->repo->updateStatus($id, 'submitted');
    }

    public function approve(int $id, int $approvedBy): void
    {
        $req = $this->repo->findById($id);
        if (!$req) throw new AppException('Requisition not found', 'NOT_FOUND', 404, 'Requisition not found.');
        if ($req['status'] !== 'submitted') throw new AppException('Can only approve submitted requisitions', 'INVALID_STATE', 409, 'Requisition is not in submitted state.');

        // Allocate budget if budget_id is set
        if ($req['budget_id']) {
            $this->budgetService->allocate((int)$req['budget_id'], (float)$req['total_amount']);
        }

        $this->repo->updateStatus($id, 'approved', $approvedBy);
        $this->logger->info('Requisition approved', ['id' => $id, 'approved_by' => $approvedBy]);
    }

    public function reject(int $id, int $rejectedBy, string $reason): void
    {
        $req = $this->repo->findById($id);
        if (!$req) throw new AppException('Requisition not found', 'NOT_FOUND', 404, 'Requisition not found.');
        if ($req['status'] !== 'submitted') throw new AppException('Can only reject submitted requisitions', 'INVALID_STATE', 409, 'Requisition is not in submitted state.');

        $this->repo->updateStatus($id, 'rejected', null, $reason);
        $this->logger->info('Requisition rejected', ['id' => $id, 'rejected_by' => $rejectedBy]);
    }

    public function count(?string $status = null, ?int $departmentId = null, ?int $requestedBy = null): int
    {
        return $this->repo->count($status, $departmentId, $requestedBy);
    }
}
