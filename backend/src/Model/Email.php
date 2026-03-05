<?php

declare(strict_types=1);

namespace App\Model;

class Email
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $vendorId,
        public readonly string $outlookMsgId,
        public readonly string $fromAddress,
        public readonly ?string $fromName,
        public readonly ?string $subject,
        public readonly string $receivedAt,
        public readonly ?string $bodyPreview,
        public readonly bool $hasAttachments,
        public readonly bool $hasInvoice,
        public readonly string $processingStatus,
        public readonly ?string $errorMessage,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            vendorId: $row['vendor_id'] ? (int)$row['vendor_id'] : null,
            outlookMsgId: $row['outlook_msg_id'],
            fromAddress: $row['from_address'],
            fromName: $row['from_name'] ?? null,
            subject: $row['subject'] ?? null,
            receivedAt: $row['received_at'],
            bodyPreview: $row['body_preview'] ?? null,
            hasAttachments: (bool)$row['has_attachments'],
            hasInvoice: (bool)$row['has_invoice'],
            processingStatus: $row['processing_status'],
            errorMessage: $row['error_message'] ?? null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
