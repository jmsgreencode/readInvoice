<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\BudgetService;
use App\Security\InputValidator;

class BudgetController
{
    private BudgetService $service;

    public function __construct(BudgetService $service)
    {
        $this->service = $service;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $fiscalYear = isset($params['fiscal_year']) ? (int)$params['fiscal_year'] : null;
        $departmentId = isset($params['department_id']) ? (int)$params['department_id'] : null;

        $budgets = $this->service->list($fiscalYear, $departmentId);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['budgets' => $budgets]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $budget = $this->service->get($id);
        if (!$budget) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'Budget not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['budget' => $budget]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $departmentId = (int)($body['department_id'] ?? 0);
        $fiscalYear = (int)($body['fiscal_year'] ?? 0);
        $totalAmount = (float)($body['total_amount'] ?? 0);
        $currency = $body['currency'] ?? 'USD';

        $id = $this->service->create($departmentId, $fiscalYear, $totalAmount, $currency);
        $budget = $this->service->get($id);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['budget' => $budget]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];
        $totalAmount = (float)($body['total_amount'] ?? 0);

        $this->service->update($id, $totalAmount);
        $budget = $this->service->get($id);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['budget' => $budget]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function utilization(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $data = $this->service->getUtilization($id);

        $response->getBody()->write(json_encode(['success' => true, 'data' => $data]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
