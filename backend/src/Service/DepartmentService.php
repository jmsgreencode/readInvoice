<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DepartmentRepository;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class DepartmentService
{
    private DepartmentRepository $repo;
    private StructuredLogger $logger;

    public function __construct(DepartmentRepository $repo, StructuredLogger $logger)
    {
        $this->repo = $repo;
        $this->logger = $logger;
    }

    public function list(): array
    {
        return $this->repo->findAll();
    }

    public function get(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function create(string $name, string $code, ?int $managerUserId = null): int
    {
        return $this->repo->create($name, $code, $managerUserId);
    }

    public function update(int $id, string $name, string $code, ?int $managerUserId = null, bool $isActive = true): void
    {
        $dept = $this->repo->findById($id);
        if (!$dept) {
            throw new AppException('Department not found', 'NOT_FOUND', 404, 'Department not found.');
        }
        $this->repo->update($id, $name, $code, $managerUserId, $isActive);
    }

    public function delete(int $id): void
    {
        $this->repo->delete($id);
    }
}
