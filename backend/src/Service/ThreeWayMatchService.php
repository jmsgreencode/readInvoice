<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\MatchResultRepository;
use App\Repository\PurchaseOrderRepository;
use App\Repository\GoodsReceivedNoteRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ComplianceSettingsRepository;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class ThreeWayMatchService
{
    private MatchResultRepository $matchRepo;
    private PurchaseOrderRepository $poRepo;
    private GoodsReceivedNoteRepository $grnRepo;
    private InvoiceRepository $invoiceRepo;
    private ComplianceSettingsRepository $settingsRepo;
    private StructuredLogger $logger;

    public function __construct(
        MatchResultRepository $matchRepo,
        PurchaseOrderRepository $poRepo,
        GoodsReceivedNoteRepository $grnRepo,
        InvoiceRepository $invoiceRepo,
        ComplianceSettingsRepository $settingsRepo,
        StructuredLogger $logger
    ) {
        $this->matchRepo = $matchRepo;
        $this->poRepo = $poRepo;
        $this->grnRepo = $grnRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->settingsRepo = $settingsRepo;
        $this->logger = $logger;
    }

    public function list(int $offset = 0, int $limit = 25, ?string $status = null): array
    {
        return $this->matchRepo->findAll($offset, $limit, $status);
    }

    public function get(int $id): ?array
    {
        return $this->matchRepo->findById($id);
    }

    public function runMatch(int $invoiceId, ?int $matchedBy = null): array
    {
        $invoice = $this->invoiceRepo->findById($invoiceId);
        if (!$invoice) throw new AppException('Invoice not found', 'NOT_FOUND', 404, 'Invoice not found.');
        if (!$invoice['po_id']) throw new AppException('Invoice not linked to a PO', 'VALIDATION', 400, 'Invoice must be linked to a purchase order.');

        $poId = (int)$invoice['po_id'];
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new AppException('PO not found', 'NOT_FOUND', 404, 'Linked purchase order not found.');

        $tolerancePct = $this->settingsRepo->getFloat('match_tolerance_pct', 2.0);

        $poAmount = (float)$po['total_amount'];
        $invoiceAmount = (float)$invoice['total_amount'];
        $grnAmount = $this->grnRepo->getTotalReceivedForPo($poId);

        // Find the latest confirmed GRN for this PO
        $grns = $this->grnRepo->findByPoId($poId);
        $latestGrnId = null;
        foreach ($grns as $grn) {
            if ($grn['status'] === 'confirmed') {
                $latestGrnId = (int)$grn['id'];
                break;
            }
        }

        $discrepancies = [];

        // PO vs Invoice comparison
        $poInvDiff = abs($poAmount - $invoiceAmount);
        $poInvPct = $poAmount > 0 ? ($poInvDiff / $poAmount) * 100 : 0;
        if ($poInvPct > $tolerancePct) {
            $discrepancies[] = [
                'type' => 'po_vs_invoice',
                'po_amount' => $poAmount,
                'invoice_amount' => $invoiceAmount,
                'difference' => $poInvDiff,
                'difference_pct' => round($poInvPct, 2),
            ];
        }

        // GRN vs Invoice comparison (if GRN exists)
        if ($grnAmount > 0) {
            $grnInvDiff = abs($grnAmount - $invoiceAmount);
            $grnInvPct = $grnAmount > 0 ? ($grnInvDiff / $grnAmount) * 100 : 0;
            if ($grnInvPct > $tolerancePct) {
                $discrepancies[] = [
                    'type' => 'grn_vs_invoice',
                    'grn_amount' => $grnAmount,
                    'invoice_amount' => $invoiceAmount,
                    'difference' => $grnInvDiff,
                    'difference_pct' => round($grnInvPct, 2),
                ];
            }
        }

        // PO vs GRN comparison
        if ($grnAmount > 0) {
            $poGrnDiff = abs($poAmount - $grnAmount);
            $poGrnPct = $poAmount > 0 ? ($poGrnDiff / $poAmount) * 100 : 0;
            if ($poGrnPct > $tolerancePct) {
                $discrepancies[] = [
                    'type' => 'po_vs_grn',
                    'po_amount' => $poAmount,
                    'grn_amount' => $grnAmount,
                    'difference' => $poGrnDiff,
                    'difference_pct' => round($poGrnPct, 2),
                ];
            }
        }

        // Determine status
        if (empty($discrepancies)) {
            $status = 'matched';
        } elseif ($grnAmount === 0.0) {
            $status = 'partial';
        } else {
            $status = 'mismatched';
        }

        $matchId = $this->matchRepo->create([
            'po_id' => $poId,
            'grn_id' => $latestGrnId,
            'invoice_id' => $invoiceId,
            'status' => $status,
            'po_amount' => $poAmount,
            'grn_amount' => $grnAmount > 0 ? $grnAmount : null,
            'invoice_amount' => $invoiceAmount,
            'tolerance_pct' => $tolerancePct,
            'discrepancies' => $discrepancies,
            'matched_by' => $matchedBy,
        ]);

        $result = $this->matchRepo->findById($matchId);
        $this->logger->info('3-way match completed', ['match_id' => $matchId, 'status' => $status, 'invoice_id' => $invoiceId]);

        return $result;
    }

    public function count(?string $status = null): int
    {
        return $this->matchRepo->count($status);
    }
}
