<?php

declare(strict_types=1);

namespace App\DTO;

use App\Model\Invoice;

class InvoiceDTO implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly int $emailId,
        public readonly ?int $vendorId,
        public readonly ?string $vendorName,
        public readonly ?string $invoiceNumber,
        public readonly ?string $invoiceDate,
        public readonly ?string $dueDate,
        public readonly ?float $totalAmount,
        public readonly string $currency,
        public readonly ?string $pdfOriginalName,
        public readonly ?float $extractionConfidence,
        public readonly string $extractionStatus,
        public readonly string $createdAt,
    ) {}

    public static function fromModel(Invoice $invoice, ?string $vendorName = null): self
    {
        return new self(
            id: $invoice->id,
            emailId: $invoice->emailId,
            vendorId: $invoice->vendorId,
            vendorName: $vendorName,
            invoiceNumber: $invoice->invoiceNumber,
            invoiceDate: $invoice->invoiceDate,
            dueDate: $invoice->dueDate,
            totalAmount: $invoice->totalAmount,
            currency: $invoice->currency,
            pdfOriginalName: $invoice->pdfOriginalName,
            extractionConfidence: $invoice->extractionConfidence,
            extractionStatus: $invoice->extractionStatus,
            createdAt: $invoice->createdAt,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'email_id' => $this->emailId,
            'vendor_id' => $this->vendorId,
            'vendor_name' => $this->vendorName,
            'invoice_number' => $this->invoiceNumber,
            'invoice_date' => $this->invoiceDate,
            'due_date' => $this->dueDate,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'pdf_original_name' => $this->pdfOriginalName,
            'extraction_confidence' => $this->extractionConfidence,
            'extraction_status' => $this->extractionStatus,
            'created_at' => $this->createdAt,
        ];
    }
}
