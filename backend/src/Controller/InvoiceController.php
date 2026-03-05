<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Repository\InvoiceRepository;
use App\Service\PdfService;
use App\Service\VendorService;
use App\Service\InvoiceExtractionService;
use App\Security\InputValidator;
use App\Exception\AppException;

class InvoiceController
{
    private InvoiceRepository $invoiceRepo;
    private PdfService $pdfService;
    private VendorService $vendorService;
    private InvoiceExtractionService $extractionService;

    public function __construct(
        InvoiceRepository $invoiceRepo,
        PdfService $pdfService,
        VendorService $vendorService,
        InvoiceExtractionService $extractionService
    ) {
        $this->invoiceRepo = $invoiceRepo;
        $this->pdfService = $pdfService;
        $this->vendorService = $vendorService;
        $this->extractionService = $extractionService;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);

        $vendorId = isset($params['vendor_id']) ? InputValidator::validateId($params['vendor_id'], 'vendor_id') : null;
        $status = $params['status'] ?? null;
        $dateFrom = isset($params['date_from']) ? InputValidator::validateDate($params['date_from'], 'date_from') : null;
        $dateTo = isset($params['date_to']) ? InputValidator::validateDate($params['date_to'], 'date_to') : null;

        $invoices = $this->invoiceRepo->findAll(
            $pagination['offset'], $pagination['per_page'],
            $vendorId, $status, $dateFrom, $dateTo
        );
        $total = $this->invoiceRepo->count($vendorId, $status);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'invoices' => $invoices,
                'pagination' => [
                    'page' => $pagination['page'],
                    'per_page' => $pagination['per_page'],
                    'total' => $total,
                    'total_pages' => (int)ceil($total / $pagination['per_page']),
                ],
            ],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $invoice = $this->invoiceRepo->findById($id);

        if (!$invoice) {
            throw new AppException('Invoice not found', 'NOT_FOUND', 404, 'Invoice not found.');
        }

        // Remove raw OCR text for non-admin users
        $userRole = $request->getAttribute('user_role', 'user');
        if ($userRole !== 'admin') {
            unset($invoice['ocr_raw_text']);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['invoice' => $invoice],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $invoice = $this->invoiceRepo->findById($id);

        if (!$invoice) {
            throw new AppException('Invoice not found', 'NOT_FOUND', 404, 'Invoice not found.');
        }

        $body = $request->getParsedBody() ?? [];
        $updateData = [];

        if (isset($body['invoice_number'])) {
            $updateData['invoice_number'] = InputValidator::validateString($body['invoice_number'], 'invoice_number', 0, 255);
        }
        if (isset($body['invoice_date'])) {
            $updateData['invoice_date'] = InputValidator::validateDate($body['invoice_date'], 'invoice_date');
        }
        if (isset($body['due_date'])) {
            $updateData['due_date'] = InputValidator::validateDate($body['due_date'], 'due_date');
        }
        if (isset($body['total_amount'])) {
            $amount = filter_var($body['total_amount'], FILTER_VALIDATE_FLOAT);
            if ($amount === false || $amount < 0) {
                throw new \App\Exception\ValidationException(['total_amount' => 'Must be a positive number']);
            }
            $updateData['total_amount'] = $amount;
        }
        if (isset($body['currency'])) {
            $updateData['currency'] = InputValidator::validateString($body['currency'], 'currency', 3, 3);
        }

        $this->invoiceRepo->update($id, $updateData);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['message' => 'Invoice updated'],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $body = $request->getParsedBody() ?? [];

        if (empty($body['vendor_id'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'Vendor ID is required'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $vendorId = InputValidator::validateId($body['vendor_id'], 'vendor_id');

        // Verify vendor exists
        $vendor = $this->vendorService->get($vendorId);
        if (!$vendor) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'Vendor not found'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        if (empty($uploadedFiles['pdf'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'PDF file is required'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $uploadedFile = $uploadedFiles['pdf'];
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'File upload failed'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Move to temp location for processing
        $tmpPath = tempnam(sys_get_temp_dir(), 'invoice_');
        $uploadedFile->moveTo($tmpPath);

        try {
            $content = file_get_contents($tmpPath);

            // Validate PDF magic bytes
            if (strlen($content) < 4 || substr($content, 0, 4) !== '%PDF') {
                throw new \RuntimeException('File is not a valid PDF');
            }

            // Store the PDF
            $sha256 = hash('sha256', $content);
            $originalName = $uploadedFile->getClientFilename() ?? 'upload.pdf';
            $safeFilename = InputValidator::sanitizeFilename($originalName);
            $date = date('Y-m-d');
            $uploadDir = $this->pdfService->getUploadDir();
            $directory = "{$uploadDir}/{$vendorId}/{$date}";

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $storedFilename = "{$sha256}.pdf";
            $fullPath = "{$directory}/{$storedFilename}";

            if (!copy($tmpPath, $fullPath)) {
                throw new \RuntimeException('Failed to store PDF file');
            }

            $relativePath = "{$vendorId}/{$date}/{$storedFilename}";

            // Try OCR extraction
            $extractedData = [];
            try {
                $extractedData = $this->extractionService->extract($fullPath);
            } catch (\Throwable $e) {
                // OCR failure is non-fatal
            }

            // Create invoice record (no email_id since this is a manual upload)
            $fields = $extractedData['data'] ?? [];
            $invoiceData = [
                'email_id' => null,
                'vendor_id' => $vendorId,
                'invoice_number' => $fields['invoice_number'] ?? null,
                'invoice_date' => $fields['invoice_date'] ?? null,
                'due_date' => $fields['due_date'] ?? null,
                'total_amount' => $fields['total_amount'] ?? null,
                'currency' => $fields['currency'] ?? 'USD',
                'pdf_path' => $relativePath,
                'pdf_sha256' => $sha256,
                'pdf_original_name' => $safeFilename,
                'ocr_raw_text' => $extractedData['raw_text'] ?? null,
                'extraction_confidence' => $extractedData['confidence'] ?? null,
                'extraction_status' => !empty($extractedData) ? 'completed' : 'pending',
            ];

            $invoiceId = $this->invoiceRepo->create($invoiceData);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'invoice_id' => $invoiceId,
                    'message' => 'Invoice uploaded successfully',
                    'extraction' => $extractedData,
                ],
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    public function downloadPdf(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $invoice = $this->invoiceRepo->findById($id);

        if (!$invoice) {
            throw new AppException('Invoice not found', 'NOT_FOUND', 404, 'Invoice not found.');
        }

        $fullPath = $this->pdfService->getFullPath($invoice['pdf_path']);

        if (!file_exists($fullPath)) {
            throw new AppException('PDF file not found', 'NOT_FOUND', 404, 'PDF file not found.');
        }

        $stream = fopen($fullPath, 'rb');
        $response = $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . ($invoice['pdf_original_name'] ?? 'invoice.pdf') . '"')
            ->withHeader('Content-Length', (string)filesize($fullPath))
            ->withHeader('X-PDF-SHA256', $invoice['pdf_sha256']);

        $response->getBody()->write(stream_get_contents($stream));
        fclose($stream);

        return $response;
    }
}
