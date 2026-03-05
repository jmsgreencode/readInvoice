<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VendorRepository;
use App\Logging\StructuredLogger;

class VendorService
{
    private VendorRepository $vendorRepo;
    private StructuredLogger $logger;

    public function __construct(VendorRepository $vendorRepo, StructuredLogger $logger)
    {
        $this->vendorRepo = $vendorRepo;
        $this->logger = $logger;
    }

    /**
     * Find or create a vendor based on email domain.
     */
    public function resolveFromEmail(string $emailAddress, ?string $displayName = null): int
    {
        $domain = $this->extractDomain($emailAddress);

        $existing = $this->vendorRepo->findByDomain($domain);
        if ($existing) {
            return (int)$existing['id'];
        }

        $vendorName = $displayName ?: $this->domainToName($domain);
        $vendorId = $this->vendorRepo->create($vendorName, $domain, $emailAddress);

        $this->logger->info('New vendor created from email', [
            'vendor_id' => $vendorId,
            'domain' => $domain,
            'name' => $vendorName,
        ]);

        return $vendorId;
    }

    public function createManual(string $name, ?string $domain = null, ?string $contactEmail = null): int
    {
        // Check if vendor with same name already exists
        $existing = $this->vendorRepo->findByName($name);
        if ($existing) {
            throw new \RuntimeException('A vendor with this name already exists');
        }

        $vendorId = $this->vendorRepo->create($name, $domain, $contactEmail);

        $this->logger->info('Vendor created manually', [
            'vendor_id' => $vendorId,
            'name' => $name,
        ]);

        return $vendorId;
    }

    public function list(int $page = 1, int $perPage = 25, ?string $search = null): array
    {
        $offset = ($page - 1) * $perPage;
        $vendors = $this->vendorRepo->findAll($offset, $perPage, $search);
        $total = $this->vendorRepo->count($search);

        return [
            'vendors' => $vendors,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    public function get(int $id): ?array
    {
        return $this->vendorRepo->findById($id);
    }

    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);
        return strtolower(end($parts));
    }

    private function domainToName(string $domain): string
    {
        $name = explode('.', $domain)[0];
        return ucfirst($name);
    }
}
