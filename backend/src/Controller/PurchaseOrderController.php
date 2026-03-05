<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\PurchaseOrderService;
use App\Security\InputValidator;

class PurchaseOrderController
{
    private PurchaseOrderService $service;

    public function __construct(PurchaseOrderService $service)
    {
        $this->service = $service;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $status = $params['status'] ?? null;
        $vendorId = isset($params['vendor_id']) ? (int)$params['vendor_id'] : null;

        $orders = $this->service->list($pagination['offset'], $pagination['per_page'], $status, $vendorId);
        $total = $this->service->count($status, $vendorId);

        $response->getBody()->write(json_encode(['success' => true, 'data' => [
            'purchase_orders' => $orders,
            'pagination' => ['page' => $pagination['page'], 'per_page' => $pagination['per_page'], 'total' => $total, 'total_pages' => (int)ceil($total / $pagination['per_page'])],
        ]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $po = $this->service->get($id);
        if (!$po) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'Purchase order not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['purchase_order' => $po]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $userId = (int)$request->getAttribute('user_id');

        // If requisition_id provided, create from requisition
        if (isset($body['requisition_id'])) {
            $id = $this->service->createFromRequisition((int)$body['requisition_id'], $userId);
        } else {
            $data = [
                'vendor_id' => (int)($body['vendor_id'] ?? 0),
                'notes' => $body['notes'] ?? null,
                'currency' => $body['currency'] ?? 'USD',
            ];
            $lineItems = $body['line_items'] ?? [];
            $id = $this->service->create($data, $lineItems, $userId);
        }

        $po = $this->service->get($id);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['purchase_order' => $po]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];

        if (isset($body['status']) && $body['status'] === 'issued') {
            $this->service->issue($id);
        }

        $po = $this->service->get($id);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['purchase_order' => $po]]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
