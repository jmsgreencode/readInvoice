<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VendorRequestRepository;
use App\Repository\VendorRepository;
use App\Database\Connection;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class VendorRequestService
{
    private VendorRequestRepository $reqRepo;
    private VendorRepository $vendorRepo;
    private Connection $db;
    private StructuredLogger $logger;

    public function __construct(VendorRequestRepository $reqRepo, VendorRepository $vendorRepo, Connection $db, StructuredLogger $logger)
    {
        $this->reqRepo = $reqRepo;
        $this->vendorRepo = $vendorRepo;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function list(int $offset = 0, int $limit = 25, ?string $status = null, ?int $requestedBy = null): array
    {
        return $this->reqRepo->findAll($offset, $limit, $status, $requestedBy);
    }

    public function get(int $id): ?array
    {
        return $this->reqRepo->findById($id);
    }

    public function create(array $data): int
    {
        $data['request_number'] = $this->reqRepo->generateNumber();
        return $this->reqRepo->create($data);
    }

    public function update(int $id, array $data): void
    {
        $req = $this->reqRepo->findById($id);
        if (!$req) throw new AppException('Vendor request not found', 'NOT_FOUND', 404, 'Vendor request not found.');
        if ($req['status'] !== 'draft') throw new AppException('Can only edit draft requests', 'INVALID_STATE', 409, 'Request is not in draft state.');
        $this->reqRepo->update($id, $data);
    }

    public function submit(int $id): void
    {
        $req = $this->reqRepo->findById($id);
        if (!$req) throw new AppException('Vendor request not found', 'NOT_FOUND', 404, 'Vendor request not found.');
        if ($req['status'] !== 'draft') throw new AppException('Can only submit draft requests', 'INVALID_STATE', 409, 'Request is not in draft state.');
        $this->reqRepo->updateStatus($id, 'submitted');
    }

    public function review(int $id, int $reviewedBy, string $decision, ?string $notes = null): void
    {
        $req = $this->reqRepo->findById($id);
        if (!$req) throw new AppException('Vendor request not found', 'NOT_FOUND', 404, 'Vendor request not found.');
        if (!in_array($req['status'], ['submitted', 'under_review'])) {
            throw new AppException('Can only review submitted requests', 'INVALID_STATE', 409, 'Request is not in a reviewable state.');
        }

        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $this->reqRepo->updateStatus($id, $status, $reviewedBy, $notes);
    }

    public function promote(int $id, int $promotedBy): int
    {
        $req = $this->reqRepo->findById($id);
        if (!$req) throw new AppException('Vendor request not found', 'NOT_FOUND', 404, 'Vendor request not found.');
        if ($req['status'] !== 'approved') throw new AppException('Can only promote approved requests', 'INVALID_STATE', 409, 'Request is not approved.');
        if ($req['promoted_vendor_id']) throw new AppException('Already promoted', 'CONFLICT', 409, 'Already promoted to vendor.');

        $this->db->beginTransaction();
        try {
            $vendorId = $this->vendorRepo->create($req['vendor_name'], null, $req['vendor_contact_email']);
            $this->reqRepo->markPromoted($id, $vendorId);
            $this->db->commit();

            $this->logger->info('Vendor request promoted', ['request_id' => $id, 'vendor_id' => $vendorId]);
            return $vendorId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
