<?php

declare(strict_types=1);

namespace App\Model;

class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly string $role,
        public readonly bool $isActive,
        public readonly ?string $lastLoginAt,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            username: $row['username'],
            passwordHash: $row['password_hash'],
            role: $row['role'] ?? 'user',
            isActive: (bool)$row['is_active'],
            lastLoginAt: $row['last_login_at'] ?? null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
