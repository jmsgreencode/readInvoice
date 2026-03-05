<?php

declare(strict_types=1);

namespace App\Model;

class Vendor
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $domain,
        public readonly ?string $contactEmail,
        public readonly ?string $notes,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            name: $row['name'],
            domain: $row['domain'] ?? null,
            contactEmail: $row['contact_email'] ?? null,
            notes: $row['notes'] ?? null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
