<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Repository\InvoiceRepository;
use App\Service\PdfService;
use App\Security\InputValidator;
use App\Exception\AppException;

class InvoiceController
{
    private InvoiceRepository $invoiceRepo;
    private PdfService $pdfService;

    public function __construct(InvoiceRepository $invoiceRepo, PdfService $pdfService)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->pdfService = $pdfService;
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
