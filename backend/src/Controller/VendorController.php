<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\VendorService;
use App\Service\VendorLifecycleService;
use App\Service\VendorDocumentService;
use App\Repository\EmailRepository;
use App\Repository\InvoiceRepository;
use App\Security\InputValidator;
use App\Exception\AppException;

class VendorController
{
    private VendorService $vendorService;
    private EmailRepository $emailRepo;
    private InvoiceRepository $invoiceRepo;
    private ?VendorLifecycleService $lifecycleService = null;
    private ?VendorDocumentService $documentService = null;

    public function __construct(
        VendorService $vendorService,
        EmailRepository $emailRepo,
        InvoiceRepository $invoiceRepo
    ) {
        $this->vendorService = $vendorService;
        $this->emailRepo = $emailRepo;
        $this->invoiceRepo = $invoiceRepo;
    }

    public function setLifecycleService(VendorLifecycleService $svc): void { $this->lifecycleService = $svc; }
    public function setDocumentService(VendorDocumentService $svc): void { $this->documentService = $svc; }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $search = isset($params['search']) ? trim($params['search']) : null;

        $result = $this->vendorService->list($pagination['page'], $pagination['per_page'], $search);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $result,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $vendor = $this->vendorService->get($id);

        if (!$vendor) {
            throw new AppException('Vendor not found', 'NOT_FOUND', 404, 'Vendor not found.');
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['vendor' => $vendor],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function emails(Request $request, Response $response, array $args): Response
    {
        $vendorId = InputValidator::validateId($args['id']);
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);

        $emails = $this->emailRepo->findByVendor($vendorId, $pagination['offset'], $pagination['per_page']);
        $total = $this->emailRepo->count($vendorId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'emails' => $emails,
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

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        if (empty($body['name'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'Vendor name is required'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $name = InputValidator::validateString($body['name'], 'name', 1, 255);
        $domain = isset($body['domain']) ? InputValidator::validateString($body['domain'], 'domain', 0, 255) : null;
        $contactEmail = isset($body['contact_email']) ? InputValidator::validateEmail($body['contact_email']) : null;

        try {
            $vendorId = $this->vendorService->createManual($name, $domain, $contactEmail);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => $e->getMessage()],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        $vendor = $this->vendorService->get($vendorId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['vendor' => $vendor],
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function invoices(Request $request, Response $response, array $args): Response
    {
        $vendorId = InputValidator::validateId($args['id']);
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);

        $invoices = $this->invoiceRepo->findByVendor($vendorId, $pagination['offset'], $pagination['per_page']);
        $total = $this->invoiceRepo->count($vendorId);

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

    public function verify(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $userId = (int)$request->getAttribute('user_id');
        $body = $request->getParsedBody() ?? [];
        $status = $body['status'] ?? 'verified';

        $this->lifecycleService->verifyVendor($id, $userId, $status);

        if (isset($body['effective_date']) || isset($body['expiry_date'])) {
            $this->lifecycleService->updateLifecycleDates($id, $body['effective_date'] ?? null, $body['expiry_date'] ?? null);
        }
        if (isset($body['risk_rating'])) {
            $this->lifecycleService->updateRiskRating($id, $body['risk_rating']);
        }

        $vendor = $this->vendorService->get($id);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['vendor' => $vendor]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function block(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];
        $block = (bool)($body['block'] ?? true);
        $reason = $body['reason'] ?? '';

        if ($block) {
            $this->lifecycleService->blockVendor($id, $reason);
        } else {
            $this->lifecycleService->unblockVendor($id);
        }

        $vendor = $this->vendorService->get($id);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['vendor' => $vendor]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function listDocuments(Request $request, Response $response, array $args): Response
    {
        $vendorId = InputValidator::validateId($args['id']);
        $docs = $this->documentService->getDocuments($vendorId);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['documents' => $docs]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function uploadDocument(Request $request, Response $response, array $args): Response
    {
        $vendorId = InputValidator::validateId($args['id']);
        $userId = (int)$request->getAttribute('user_id');
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'No file uploaded']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $body = $request->getParsedBody() ?? [];
        $documentType = $body['document_type'] ?? 'general';
        $expiryDate = $body['expiry_date'] ?? null;

        $docId = $this->documentService->uploadDocument($vendorId, $file, $documentType, $userId, $expiryDate);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['document_id' => $docId]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
}
