<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use ReadInvoice\Frontend\Service\BackendApiClient;
use ReadInvoice\Frontend\Service\DatastarResponseBuilder;
use ReadInvoice\Frontend\Service\SessionService;

class InvoiceViewController
{
    public function __construct(
        private readonly BackendApiClient $api,
        private readonly DatastarResponseBuilder $sse,
        private readonly SessionService $session,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * SSE endpoint: returns invoice detail fragment with all extracted data.
     */
    public function invoiceDetailFragment(Request $request, Response $response, array $args): Response
    {
        $this->session->start();
        $invoiceId = (int) $args['id'];
        $user = $this->session->get('user');
        $isAdmin = ($user['role'] ?? '') === 'admin';

        try {
            $result = $this->api->get("/api/invoices/{$invoiceId}");
            $invoice = $result['data'] ?? $result;

            ob_start();
            $viewData = [
                'invoice' => $invoice,
                'isAdmin' => $isAdmin,
            ];
            extract($viewData);
            include __DIR__ . '/../View/Partial/invoice-detail.php';
            $html = ob_get_clean();

            return $this->sse->mergeFragments($response, '#main-content-area', $html);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch invoice detail', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            ob_start();
            $errorMessage = 'Unable to load invoice details. Please try again.';
            if ($isAdmin) {
                $errorDetail = $e->getMessage();
            }
            include __DIR__ . '/../View/Partial/error-toast.php';
            $html = ob_get_clean();

            return $this->sse->mergeFragments($response, '#toast-container', $html);
        }
    }

    /**
     * Proxy PDF download from backend.
     */
    public function downloadPdf(Request $request, Response $response, array $args): Response
    {
        $invoiceId = (int) $args['id'];
        $this->session->start();

        try {
            $pdfData = $this->api->getRaw("/api/invoices/{$invoiceId}/download");

            $response->getBody()->write($pdfData['body']);

            return $response
                ->withHeader('Content-Type', $pdfData['content_type'] ?? 'application/pdf')
                ->withHeader('Content-Disposition', $pdfData['content_disposition'] ?? "attachment; filename=\"invoice-{$invoiceId}.pdf\"");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to download invoice PDF', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            $response->getBody()->write(json_encode(['error' => 'Download failed']));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
