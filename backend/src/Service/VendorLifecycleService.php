<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VendorRepository;
use App\Repository\AuditLogRepository;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class VendorLifecycleService
{
    private VendorRepository $vendorRepo;
    private AuditLogRepository $auditRepo;
    private StructuredLogger $logger;

    public function __construct(VendorRepository $vendorRepo, AuditLogRepository $auditRepo, StructuredLogger $logger)
    {
        $this->vendorRepo = $vendorRepo;
        $this->auditRepo = $auditRepo;
        $this->logger = $logger;
    }

    public function verifyVendor(int $vendorId, int $verifiedBy, string $status = 'verified'): void
    {
        $vendor = $this->vendorRepo->findById($vendorId);
        if (!$vendor) {
            throw new AppException('Vendor not found', 'NOT_FOUND', 404, 'Vendor not found.');
        }

        $this->vendorRepo->updateLifecycle($vendorId, [
            'verification_status' => $status,
            'verified_by' => $verifiedBy,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logger->info('Vendor verified', ['vendor_id' => $vendorId, 'status' => $status, 'verified_by' => $verifiedBy]);
    }

    public function blockVendor(int $vendorId, string $reason): void
    {
        $vendor = $this->vendorRepo->findById($vendorId);
        if (!$vendor) {
            throw new AppException('Vendor not found', 'NOT_FOUND', 404, 'Vendor not found.');
        }

        $this->vendorRepo->updateLifecycle($vendorId, [
            'is_blocked' => 1,
            'blocked_reason' => $reason,
        ]);

        $this->logger->info('Vendor blocked', ['vendor_id' => $vendorId, 'reason' => $reason]);
    }

    public function unblockVendor(int $vendorId): void
    {
        $this->vendorRepo->updateLifecycle($vendorId, [
            'is_blocked' => 0,
            'blocked_reason' => null,
        ]);
    }

    public function updateLifecycleDates(int $vendorId, ?string $effectiveDate, ?string $expiryDate): void
    {
        $this->vendorRepo->updateLifecycle($vendorId, [
            'effective_date' => $effectiveDate,
            'expiry_date' => $expiryDate,
        ]);
    }

    public function updateRiskRating(int $vendorId, string $rating): void
    {
        $this->vendorRepo->updateLifecycle($vendorId, ['risk_rating' => $rating]);
    }

    public function getExpiringVendors(int $withinDays = 30): array
    {
        return $this->vendorRepo->findExpiring($withinDays);
    }
}
