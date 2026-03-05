<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
use App\Repository\UserRoleRepository;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class RbacService
{
    private RoleRepository $roleRepo;
    private PermissionRepository $permissionRepo;
    private UserRoleRepository $userRoleRepo;
    private StructuredLogger $logger;

    public function __construct(
        RoleRepository $roleRepo,
        PermissionRepository $permissionRepo,
        UserRoleRepository $userRoleRepo,
        StructuredLogger $logger
    ) {
        $this->roleRepo = $roleRepo;
        $this->permissionRepo = $permissionRepo;
        $this->userRoleRepo = $userRoleRepo;
        $this->logger = $logger;
    }

    public function getUserPermissions(int $userId): array
    {
        return $this->userRoleRepo->getUserPermissions($userId);
    }

    public function getUserRoles(int $userId): array
    {
        return $this->userRoleRepo->getUserRoles($userId);
    }

    public function hasPermission(int $userId, string $permissionName): bool
    {
        $permissions = $this->getUserPermissions($userId);
        return in_array($permissionName, $permissions, true);
    }

    public function assignRole(int $userId, int $roleId, ?int $assignedBy = null): void
    {
        $role = $this->roleRepo->findById($roleId);
        if (!$role) {
            throw new AppException('Role not found', 'NOT_FOUND', 404, 'Role not found.');
        }

        $conflicts = $this->checkSodConflicts($userId, $roleId);
        if (!empty($conflicts)) {
            $reasons = array_map(fn($c) => $c['reason'], $conflicts);
            throw new AppException(
                'SoD conflict: ' . implode('; ', $reasons),
                'SOD_CONFLICT',
                409,
                'Cannot assign this role due to Separation of Duties conflicts: ' . implode('; ', $reasons)
            );
        }

        $this->userRoleRepo->assignRole($userId, $roleId, $assignedBy);
        $this->logger->info('Role assigned', ['user_id' => $userId, 'role_id' => $roleId, 'assigned_by' => $assignedBy]);
    }

    public function removeRole(int $userId, int $roleId): void
    {
        $this->userRoleRepo->removeRole($userId, $roleId);
        $this->logger->info('Role removed', ['user_id' => $userId, 'role_id' => $roleId]);
    }

    public function checkSodConflicts(int $userId, int $newRoleId): array
    {
        $currentRoles = $this->userRoleRepo->getUserRoles($userId);
        $currentRoleIds = array_column($currentRoles, 'id');

        $conflicts = $this->userRoleRepo->getSodConflicts($newRoleId);
        $activeConflicts = [];

        foreach ($conflicts as $conflict) {
            $conflictingRoleId = ($conflict['role_a_id'] == $newRoleId)
                ? $conflict['role_b_id']
                : $conflict['role_a_id'];

            if (in_array($conflictingRoleId, $currentRoleIds)) {
                $activeConflicts[] = $conflict;
            }
        }

        return $activeConflicts;
    }

    // Role CRUD
    public function listRoles(): array
    {
        return $this->roleRepo->findAll();
    }

    public function getRole(int $id): ?array
    {
        $role = $this->roleRepo->findById($id);
        if ($role) {
            $role['permissions'] = $this->roleRepo->getPermissions($id);
        }
        return $role;
    }

    public function createRole(string $name, string $displayName, ?string $description = null): int
    {
        $existing = $this->roleRepo->findByName($name);
        if ($existing) {
            throw new AppException('Role name already exists', 'CONFLICT', 409, 'A role with this name already exists.');
        }
        return $this->roleRepo->create($name, $displayName, $description);
    }

    public function updateRole(int $id, string $name, string $displayName, ?string $description = null): void
    {
        $role = $this->roleRepo->findById($id);
        if (!$role) {
            throw new AppException('Role not found', 'NOT_FOUND', 404, 'Role not found.');
        }
        if ($role['is_system']) {
            throw new AppException('Cannot modify system role', 'FORBIDDEN', 403, 'System roles cannot be modified.');
        }
        $this->roleRepo->update($id, $name, $displayName, $description);
    }

    public function deleteRole(int $id): void
    {
        $role = $this->roleRepo->findById($id);
        if (!$role) {
            throw new AppException('Role not found', 'NOT_FOUND', 404, 'Role not found.');
        }
        if ($role['is_system']) {
            throw new AppException('Cannot delete system role', 'FORBIDDEN', 403, 'System roles cannot be deleted.');
        }
        $this->roleRepo->delete($id);
    }

    public function setRolePermissions(int $roleId, array $permissionIds): void
    {
        $role = $this->roleRepo->findById($roleId);
        if (!$role) {
            throw new AppException('Role not found', 'NOT_FOUND', 404, 'Role not found.');
        }
        $this->roleRepo->setPermissions($roleId, $permissionIds);
    }

    public function listPermissions(): array
    {
        return $this->permissionRepo->findAll();
    }
}
