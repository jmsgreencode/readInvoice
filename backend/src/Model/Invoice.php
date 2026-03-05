<?php

declare(strict_types=1);

namespace App\Model;

class Invoice
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $emailId,
        public readonly ?int $vendorId,
        public readonly ?string $invoiceNumber,
        public readonly ?string $invoiceDate,
        public readonly ?string $dueDate,
        public readonly ?float $totalAmount,
        public readonly string $currency,
        public readonly string $pdfPath,
        public readonly string $pdfSha256,
        public readonly ?string $pdfOriginalName,
        public readonly ?string $ocrRawText,
        public readonly ?float $extractionConfidence,
        public readonly string $extractionStatus,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            emailId: (int)$row['email_id'],
            vendorId: $row['vendor_id'] ? (int)$row['vendor_id'] : null,
            invoiceNumber: $row['invoice_number'] ?? null,
            invoiceDate: $row['invoice_date'] ?? null,
            dueDate: $row['due_date'] ?? null,
            totalAmount: $row['total_amount'] !== null ? (float)$row['total_amount'] : null,
            currency: $row['currency'] ?? 'USD',
            pdfPath: $row['pdf_path'],
            pdfSha256: $row['pdf_sha256'],
            pdfOriginalName: $row['pdf_original_name'] ?? null,
            ocrRawText: $row['ocr_raw_text'] ?? null,
            extractionConfidence: $row['extraction_confidence'] !== null ? (float)$row['extraction_confidence'] : null,
            extractionStatus: $row['extraction_status'] ?? 'pending',
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
