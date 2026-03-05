<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ComplianceSettingsRepository;
use App\Repository\ComplianceAlertRepository;
use App\Repository\VendorRepository;
use App\Repository\VendorDocumentRepository;
use App\Repository\BudgetRepository;
use App\Logging\StructuredLogger;

class ComplianceService
{
    private ComplianceSettingsRepository $settingsRepo;
    private ComplianceAlertRepository $alertRepo;
    private VendorRepository $vendorRepo;
    private VendorDocumentRepository $docRepo;
    private BudgetRepository $budgetRepo;
    private StructuredLogger $logger;

    public function __construct(
        ComplianceSettingsRepository $settingsRepo,
        ComplianceAlertRepository $alertRepo,
        VendorRepository $vendorRepo,
        VendorDocumentRepository $docRepo,
        BudgetRepository $budgetRepo,
        StructuredLogger $logger
    ) {
        $this->settingsRepo = $settingsRepo;
        $this->alertRepo = $alertRepo;
        $this->vendorRepo = $vendorRepo;
        $this->docRepo = $docRepo;
        $this->budgetRepo = $budgetRepo;
        $this->logger = $logger;
    }

    public function getSettings(): array
    {
        return $this->settingsRepo->getAll();
    }

    public function updateSetting(string $key, string $value, ?int $updatedBy = null): void
    {
        $this->settingsRepo->set($key, $value, $updatedBy);
    }

    public function listAlerts(int $offset = 0, int $limit = 25, ?string $type = null, ?bool $resolved = null): array
    {
        return $this->alertRepo->findAll($offset, $limit, $type, $resolved);
    }

    public function resolveAlert(int $id, int $resolvedBy): void
    {
        $this->alertRepo->resolve($id, $resolvedBy);
    }

    public function countAlerts(?string $type = null, ?bool $resolved = null): int
    {
        return $this->alertRepo->count($type, $resolved);
    }

    /**
     * Run all compliance checks. Called by the compliance-worker cron.
     */
    public function runAllChecks(): array
    {
        $results = [
            'vendor_expiry' => $this->checkVendorExpiry(),
            'document_expiry' => $this->checkDocumentExpiry(),
            'budget_thresholds' => $this->checkBudgetThresholds(),
        ];

        if ($this->settingsRepo->getBool('auto_block_expired_vendors', false)) {
            $results['auto_blocked'] = $this->autoBlockExpiredVendors();
        }

        return $results;
    }

    public function checkVendorExpiry(): int
    {
        $warningDays = (int)$this->settingsRepo->get('vendor_expiry_warning_days') ?: 30;
        $vendors = $this->vendorRepo->findExpiring($warningDays);
        $count = 0;

        foreach ($vendors as $vendor) {
            if (!$this->alertRepo->existsUnresolved('vendor_expiry', 'vendor', (int)$vendor['id'])) {
                $this->alertRepo->create(
                    'vendor_expiry', 'warning', 'vendor', (int)$vendor['id'],
                    "Vendor '{$vendor['name']}' expiring soon",
                    "Vendor '{$vendor['name']}' expires on {$vendor['expiry_date']}. Review and renew."
                );
                $count++;
            }
        }

        return $count;
    }

    public function checkDocumentExpiry(): int
    {
        $warningDays = (int)$this->settingsRepo->get('document_expiry_warning_days') ?: 30;
        $docs = $this->docRepo->findExpiring($warningDays);
        $count = 0;

        foreach ($docs as $doc) {
            if (!$this->alertRepo->existsUnresolved('document_expiry', 'vendor_document', (int)$doc['id'])) {
                $this->alertRepo->create(
                    'document_expiry', 'warning', 'vendor_document', (int)$doc['id'],
                    "Document expiring for vendor '{$doc['vendor_name']}'",
                    "Document '{$doc['file_name']}' ({$doc['document_type']}) expires on {$doc['expiry_date']}."
                );
                $count++;
            }
        }

        return $count;
    }

    public function checkBudgetThresholds(): int
    {
        $warningPct = $this->settingsRepo->getFloat('budget_warning_threshold_pct', 80.0);
        $criticalPct = $this->settingsRepo->getFloat('budget_critical_threshold_pct', 95.0);
        $count = 0;

        $budgets = $this->budgetRepo->findOverThreshold($warningPct);
        foreach ($budgets as $budget) {
            $utilPct = (float)$budget['utilization_pct'];
            $severity = $utilPct >= $criticalPct ? 'critical' : 'warning';
            $alertType = 'budget_threshold';

            if (!$this->alertRepo->existsUnresolved($alertType, 'budget', (int)$budget['id'])) {
                $this->alertRepo->create(
                    $alertType, $severity, 'budget', (int)$budget['id'],
                    "Budget {$severity}: {$budget['department_name']} FY{$budget['fiscal_year']}",
                    sprintf("Budget utilization at %.1f%% (%.2f of %.2f allocated).",
                        $utilPct, $budget['allocated_amount'], $budget['total_amount'])
                );
                $count++;
            }
        }

        return $count;
    }

    public function autoBlockExpiredVendors(): int
    {
        $vendors = $this->vendorRepo->findExpired();
        $count = 0;

        foreach ($vendors as $vendor) {
            if (!(int)$vendor['is_blocked']) {
                $this->vendorRepo->updateLifecycle((int)$vendor['id'], [
                    'is_blocked' => 1,
                    'blocked_reason' => 'Auto-blocked: vendor contract expired on ' . $vendor['expiry_date'],
                ]);

                if (!$this->alertRepo->existsUnresolved('vendor_blocked', 'vendor', (int)$vendor['id'])) {
                    $this->alertRepo->create(
                        'vendor_blocked', 'critical', 'vendor', (int)$vendor['id'],
                        "Vendor '{$vendor['name']}' auto-blocked",
                        "Vendor was automatically blocked due to expired contract (expired {$vendor['expiry_date']})."
                    );
                }
                $count++;
            }
        }

        return $count;
    }
}
