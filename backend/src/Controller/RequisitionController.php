<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\RequisitionService;
use App\Security\InputValidator;

class RequisitionController
{
    private RequisitionService $service;

    public function __construct(RequisitionService $service)
    {
        $this->service = $service;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $status = $params['status'] ?? null;
        $departmentId = isset($params['department_id']) ? (int)$params['department_id'] : null;
        $requestedBy = isset($params['requested_by']) ? (int)$params['requested_by'] : null;

        $requisitions = $this->service->list($pagination['offset'], $pagination['per_page'], $status, $departmentId, $requestedBy);
        $total = $this->service->count($status, $departmentId, $requestedBy);

        $response->getBody()->write(json_encode(['success' => true, 'data' => [
            'requisitions' => $requisitions,
            'pagination' => ['page' => $pagination['page'], 'per_page' => $pagination['per_page'], 'total' => $total, 'total_pages' => (int)ceil($total / $pagination['per_page'])],
        ]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $req = $this->service->get($id);
        if (!$req) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'Requisition not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['requisition' => $req]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $userId = (int)$request->getAttribute('user_id');

        $data = [
            'department_id' => (int)($body['department_id'] ?? 0),
            'requested_by' => $userId,
            'vendor_id' => isset($body['vendor_id']) ? (int)$body['vendor_id'] : null,
            'justification' => $body['justification'] ?? null,
            'budget_id' => isset($body['budget_id']) ? (int)$body['budget_id'] : null,
            'priority' => $body['priority'] ?? 'medium',
            'currency' => $body['currency'] ?? 'USD',
        ];

        $lineItems = $body['line_items'] ?? [];
        $id = $this->service->create($data, $lineItems);
        $req = $this->service->get($id);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['requisition' => $req]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function submit(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $this->service->submit($id);
        $response->getBody()->write(json_encode(['success' => true, 'data' => null]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $userId = (int)$request->getAttribute('user_id');
        $this->service->approve($id, $userId);
        $response->getBody()->write(json_encode(['success' => true, 'data' => null]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function reject(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $userId = (int)$request->getAttribute('user_id');
        $body = $request->getParsedBody() ?? [];
        $reason = $body['reason'] ?? 'No reason provided';
        $this->service->reject($id, $userId, $reason);
        $response->getBody()->write(json_encode(['success' => true, 'data' => null]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
