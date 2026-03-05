<?php

declare(strict_types=1);

namespace App\DTO;

use App\Model\Vendor;

class VendorDTO implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $domain,
        public readonly ?string $contactEmail,
        public readonly int $emailCount,
        public readonly int $invoiceCount,
        public readonly string $createdAt,
    ) {}

    public static function fromModel(Vendor $vendor, int $emailCount = 0, int $invoiceCount = 0): self
    {
        return new self(
            id: $vendor->id,
            name: $vendor->name,
            domain: $vendor->domain,
            contactEmail: $vendor->contactEmail,
            emailCount: $emailCount,
            invoiceCount: $invoiceCount,
            createdAt: $vendor->createdAt,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'contact_email' => $this->contactEmail,
            'email_count' => $this->emailCount,
            'invoice_count' => $this->invoiceCount,
            'created_at' => $this->createdAt,
        ];
    }
}
