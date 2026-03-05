<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use ReadInvoice\Frontend\Service\BackendApiClient;
use ReadInvoice\Frontend\Service\DatastarResponseBuilder;
use ReadInvoice\Frontend\Service\SessionService;

class VendorEmailController
{
    public function __construct(
        private readonly BackendApiClient $api,
        private readonly DatastarResponseBuilder $sse,
        private readonly SessionService $session,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * SSE endpoint: returns vendor list fragment for the sidebar.
     */
    public function vendorListFragment(Request $request, Response $response): Response
    {
        $this->session->start();

        try {
            $result = $this->api->get('/api/vendors');

            ob_start();
            $viewData = ['vendors' => $result['data']['vendors'] ?? []];
            extract($viewData);
            include __DIR__ . '/../View/Partial/vendor-list.php';
            $html = ob_get_clean();

            return $this->sse->mergeFragments($response, '#vendor-list', $html);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch vendor list', [
                'error' => $e->getMessage(),
            ]);

            ob_start();
            $errorMessage = 'Unable to load vendor list. Please try again.';
            include __DIR__ . '/../View/Partial/error-toast.php';
            $html = ob_get_clean();

            return $this->sse->mergeFragments($response, '#toast-container', $html);
        }
    }

    /**
     * SSE endpoint: returns emails for a specific vendor.
     */
    public function vendorEmailsFragment(Request $request, Response $response, array $args): Response
    {
        $this->session->start();
        $vendorId = (int) $args['id'];

        try {
            $result = $this->api->get("/api/vendors/{$vendorId}/emails");
            $emails = $result['data']['emails'] ?? [];

            // Also fetch vendor details
            $vendorResult = $this->api->get("/api/vendors/{$vendorId}");
            $vendor = $vendorResult['data']['vendor'] ?? ['id' => $vendorId, 'name' => 'Unknown Vendor'];

            ob_start();
            $viewData = [
                'emails' => $emails,
                'vendor' => $vendor,
                'vendorId' => $vendorId,
            ];
            extract($viewData);
            include __DIR__ . '/../View/Partial/email-table.php';
            $html = ob_get_clean();

            return $this->sse->mergeFragments($response, '#main-content-area', $html);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch vendor emails', [
                'vendor_id' => $vendorId,
                'error' => $e->getMessage(),
            ]);

            ob_start();
            $errorMessage = 'Unable to load emails for this vendor. Please try again.';
            include __DIR__ . '/../View/Partial/error-toast.php';
            $html = ob_get_clean();

            return $this->sse->mergeFragments($response, '#toast-container', $html);
        }
    }
}
