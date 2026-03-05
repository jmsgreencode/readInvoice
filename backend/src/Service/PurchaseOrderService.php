<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\PurchaseOrderRepository;
use App\Repository\RequisitionRepository;
use App\Database\Connection;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class PurchaseOrderService
{
    private PurchaseOrderRepository $poRepo;
    private RequisitionRepository $reqRepo;
    private Connection $db;
    private StructuredLogger $logger;

    public function __construct(PurchaseOrderRepository $poRepo, RequisitionRepository $reqRepo, Connection $db, StructuredLogger $logger)
    {
        $this->poRepo = $poRepo;
        $this->reqRepo = $reqRepo;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function list(int $offset = 0, int $limit = 25, ?string $status = null, ?int $vendorId = null): array
    {
        return $this->poRepo->findAll($offset, $limit, $status, $vendorId);
    }

    public function get(int $id): ?array
    {
        $po = $this->poRepo->findById($id);
        if ($po) {
            $po['line_items'] = $this->poRepo->getLineItems($id);
        }
        return $po;
    }

    public function createFromRequisition(int $requisitionId, int $createdBy): int
    {
        $req = $this->reqRepo->findById($requisitionId);
        if (!$req) throw new AppException('Requisition not found', 'NOT_FOUND', 404, 'Requisition not found.');
        if ($req['status'] !== 'approved') throw new AppException('Requisition must be approved', 'INVALID_STATE', 409, 'Requisition is not approved.');
        if (!$req['vendor_id']) throw new AppException('Requisition must have a vendor', 'VALIDATION', 400, 'Requisition has no vendor assigned.');

        $this->db->beginTransaction();
        try {
            $poId = $this->poRepo->create([
                'po_number' => $this->poRepo->generateNumber(),
                'requisition_id' => $requisitionId,
                'vendor_id' => $req['vendor_id'],
                'created_by' => $createdBy,
                'total_amount' => $req['total_amount'],
                'currency' => $req['currency'],
            ]);

            // Copy line items from requisition to PO
            $reqItems = $this->reqRepo->getLineItems($requisitionId);
            foreach ($reqItems as $item) {
                $this->poRepo->addLineItem($poId, $item['description'], (float)$item['quantity'], (float)$item['unit_price']);
            }

            $this->db->commit();
            $this->logger->info('PO created from requisition', ['po_id' => $poId, 'requisition_id' => $requisitionId]);
            return $poId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function create(array $data, array $lineItems, int $createdBy): int
    {
        $this->db->beginTransaction();
        try {
            $data['po_number'] = $this->poRepo->generateNumber();
            $data['created_by'] = $createdBy;
            $data['total_amount'] = 0;
            $poId = $this->poRepo->create($data);

            foreach ($lineItems as $item) {
                $this->poRepo->addLineItem($poId, $item['description'], (float)$item['quantity'], (float)$item['unit_price']);
            }

            $this->poRepo->recalculateTotal($poId);
            $this->db->commit();
            return $poId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function issue(int $id): void
    {
        $po = $this->poRepo->findById($id);
        if (!$po) throw new AppException('PO not found', 'NOT_FOUND', 404, 'Purchase order not found.');
        if ($po['status'] !== 'draft') throw new AppException('Can only issue draft POs', 'INVALID_STATE', 409, 'PO is not in draft state.');
        $this->poRepo->updateStatus($id, 'issued');
    }

    public function count(?string $status = null, ?int $vendorId = null): int
    {
        return $this->poRepo->count($status, $vendorId);
    }
}
