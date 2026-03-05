<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\EmailRepository;
use App\Repository\InvoiceRepository;
use App\Repository\AuditLogRepository;
use App\Logging\StructuredLogger;

class EmailIngestionService
{
    private EmailRepository $emailRepo;
    private InvoiceRepository $invoiceRepo;
    private AuditLogRepository $auditRepo;
    private VendorService $vendorService;
    private PdfService $pdfService;
    private InvoiceExtractionService $extractionService;
    private StructuredLogger $logger;

    public function __construct(
        EmailRepository $emailRepo,
        InvoiceRepository $invoiceRepo,
        AuditLogRepository $auditRepo,
        VendorService $vendorService,
        PdfService $pdfService,
        InvoiceExtractionService $extractionService,
        StructuredLogger $logger
    ) {
        $this->emailRepo = $emailRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->auditRepo = $auditRepo;
        $this->vendorService = $vendorService;
        $this->pdfService = $pdfService;
        $this->extractionService = $extractionService;
        $this->logger = $logger;
    }

    /**
     * Ingest an email from Outlook add-in or email worker.
     *
     * @param array $payload {
     *   outlook_msg_id: string,
     *   from_address: string,
     *   from_name: ?string,
     *   subject: ?string,
     *   body: ?string,
     *   received_at: string,
     *   attachments: array<{name: string, content_type: string, content_base64: string}>
     * }
     */
    public function ingest(array $payload, ?int $userId = null, ?string $requestId = null): array
    {
        // Dedup check
        $existing = $this->emailRepo->findByOutlookMsgId($payload['outlook_msg_id']);
        if ($existing) {
            $this->logger->info('Duplicate email skipped', [
                'outlook_msg_id' => $payload['outlook_msg_id'],
                'existing_id' => $existing['id'],
            ]);
            return ['email_id' => $existing['id'], 'status' => 'duplicate'];
        }

        // Resolve vendor from sender
        $vendorId = $this->vendorService->resolveFromEmail(
            $payload['from_address'],
            $payload['from_name'] ?? null
        );

        // Check for PDF attachments
        $pdfAttachments = array_filter(
            $payload['attachments'] ?? [],
            fn($a) => ($a['content_type'] ?? '') === 'application/pdf'
                || str_ends_with(strtolower($a['name'] ?? ''), '.pdf')
        );

        $hasInvoice = !empty($pdfAttachments) && $this->looksLikeInvoice(
            $payload['subject'] ?? '',
            $payload['body'] ?? ''
        );

        // Sanitize body preview (first 500 chars, stripped)
        $bodyPreview = null;
        if (!empty($payload['body'])) {
            $bodyPreview = mb_substr(strip_tags($payload['body']), 0, 500);
        }

        // Store email record
        $emailId = $this->emailRepo->create([
            'vendor_id' => $vendorId,
            'outlook_msg_id' => $payload['outlook_msg_id'],
            'from_address' => $payload['from_address'],
            'from_name' => $payload['from_name'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'received_at' => $payload['received_at'],
            'body_preview' => $bodyPreview,
            'has_attachments' => !empty($payload['attachments']),
            'has_invoice' => $hasInvoice,
            'processing_status' => 'processing',
        ]);

        $this->logger->info('Email ingested', [
            'email_id' => $emailId,
            'vendor_id' => $vendorId,
            'has_invoice' => $hasInvoice,
            'attachment_count' => count($pdfAttachments),
        ]);

        // Process PDF attachments
        $invoices = [];
        foreach ($pdfAttachments as $attachment) {
            try {
                $invoices[] = $this->processAttachment($attachment, $emailId, $vendorId);
            } catch (\Exception $e) {
                $this->logger->error('Attachment processing failed', [
                    'email_id' => $emailId,
                    'attachment_name' => $attachment['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $status = empty($pdfAttachments) ? 'completed' : (!empty($invoices) ? 'completed' : 'failed');
        $this->emailRepo->updateStatus($emailId, $status);

        $this->auditRepo->log(
            'email.ingested',
            $userId,
            'email',
            $emailId,
            ['vendor_id' => $vendorId, 'invoices_created' => count($invoices)],
            null,
            $requestId
        );

        return [
            'email_id' => $emailId,
            'vendor_id' => $vendorId,
            'status' => $status,
            'invoices' => $invoices,
        ];
    }

    private function processAttachment(array $attachment, int $emailId, int $vendorId): array
    {
        // Store PDF
        $pdfResult = $this->pdfService->storeFromBase64(
            $attachment['content_base64'],
            $attachment['name'] ?? 'invoice.pdf',
            $vendorId
        );

        // Extract invoice data
        $fullPath = $this->pdfService->getFullPath($pdfResult['path']);
        $extraction = $this->extractionService->extract($fullPath);

        $extractionStatus = $extraction['confidence'] >= 70.0 ? 'completed' : 'manual_review';

        // Store invoice
        $invoiceId = $this->invoiceRepo->create([
            'email_id' => $emailId,
            'vendor_id' => $vendorId,
            'invoice_number' => $extraction['data']['invoice_number'],
            'invoice_date' => $extraction['data']['invoice_date'],
            'due_date' => $extraction['data']['due_date'],
            'total_amount' => $extraction['data']['total_amount'],
            'currency' => $extraction['data']['currency'],
            'pdf_path' => $pdfResult['path'],
            'pdf_sha256' => $pdfResult['sha256'],
            'pdf_original_name' => $pdfResult['filename'],
            'ocr_raw_text' => $extraction['raw_text'],
            'extraction_confidence' => $extraction['confidence'],
            'extraction_status' => $extractionStatus,
        ]);

        $this->logger->info('Invoice created from attachment', [
            'invoice_id' => $invoiceId,
            'email_id' => $emailId,
            'confidence' => $extraction['confidence'],
            'status' => $extractionStatus,
        ]);

        return [
            'invoice_id' => $invoiceId,
            'extraction_status' => $extractionStatus,
            'confidence' => $extraction['confidence'],
        ];
    }

    private function looksLikeInvoice(string $subject, string $body): bool
    {
        $keywords = ['invoice', 'payment', 'bill', 'statement', 'receipt', 'amount due', 'remittance'];
        $combined = strtolower($subject . ' ' . $body);

        foreach ($keywords as $keyword) {
            if (str_contains($combined, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
