<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\VendorService;
use App\Repository\EmailRepository;
use App\Repository\InvoiceRepository;
use App\Security\InputValidator;
use App\Exception\AppException;

class VendorController
{
    private VendorService $vendorService;
    private EmailRepository $emailRepo;
    private InvoiceRepository $invoiceRepo;

    public function __construct(
        VendorService $vendorService,
        EmailRepository $emailRepo,
        InvoiceRepository $invoiceRepo
    ) {
        $this->vendorService = $vendorService;
        $this->emailRepo = $emailRepo;
        $this->invoiceRepo = $invoiceRepo;
    }

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
}
