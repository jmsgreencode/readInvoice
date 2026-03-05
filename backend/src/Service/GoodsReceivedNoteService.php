<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\GoodsReceivedNoteRepository;
use App\Repository\PurchaseOrderRepository;
use App\Database\Connection;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class GoodsReceivedNoteService
{
    private GoodsReceivedNoteRepository $grnRepo;
    private PurchaseOrderRepository $poRepo;
    private Connection $db;
    private StructuredLogger $logger;

    public function __construct(GoodsReceivedNoteRepository $grnRepo, PurchaseOrderRepository $poRepo, Connection $db, StructuredLogger $logger)
    {
        $this->grnRepo = $grnRepo;
        $this->poRepo = $poRepo;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function list(int $offset = 0, int $limit = 25, ?int $poId = null): array
    {
        return $this->grnRepo->findAll($offset, $limit, $poId);
    }

    public function get(int $id): ?array
    {
        $grn = $this->grnRepo->findById($id);
        if ($grn) {
            $grn['line_items'] = $this->grnRepo->getLineItems($id);
        }
        return $grn;
    }

    public function create(int $poId, int $receivedBy, string $receivedDate, array $lineItems, ?string $notes = null): int
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new AppException('PO not found', 'NOT_FOUND', 404, 'Purchase order not found.');
        if (!in_array($po['status'], ['issued', 'partially_received'])) {
            throw new AppException('PO not in receivable state', 'INVALID_STATE', 409, 'PO must be issued or partially received.');
        }

        $this->db->beginTransaction();
        try {
            $grnId = $this->grnRepo->create([
                'grn_number' => $this->grnRepo->generateNumber(),
                'po_id' => $poId,
                'received_by' => $receivedBy,
                'received_date' => $receivedDate,
                'notes' => $notes,
            ]);

            foreach ($lineItems as $item) {
                $this->grnRepo->addLineItem(
                    $grnId,
                    (int)$item['po_line_item_id'],
                    (float)$item['received_qty'],
                    (float)$item['accepted_qty'],
                    (float)($item['rejected_qty'] ?? 0),
                    $item['rejection_reason'] ?? null
                );

                // Update PO line item received qty
                $poLineItems = $this->poRepo->getLineItems($poId);
                foreach ($poLineItems as $pli) {
                    if ((int)$pli['id'] === (int)$item['po_line_item_id']) {
                        $newReceivedQty = (float)$pli['received_qty'] + (float)$item['accepted_qty'];
                        $this->poRepo->updateLineItemReceivedQty((int)$pli['id'], $newReceivedQty);
                    }
                }
            }

            // Confirm GRN
            $this->grnRepo->updateStatus($grnId, 'confirmed');

            // Check if PO is fully received
            $poLineItems = $this->poRepo->getLineItems($poId);
            $fullyReceived = true;
            foreach ($poLineItems as $pli) {
                // Re-read after update
                if ((float)$pli['received_qty'] < (float)$pli['quantity']) {
                    $fullyReceived = false;
                    break;
                }
            }

            $this->poRepo->updateStatus($poId, $fullyReceived ? 'fully_received' : 'partially_received');

            $this->db->commit();
            $this->logger->info('GRN created', ['grn_id' => $grnId, 'po_id' => $poId]);
            return $grnId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
