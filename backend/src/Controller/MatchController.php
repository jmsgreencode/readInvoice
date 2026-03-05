<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ThreeWayMatchService;
use App\Security\InputValidator;

class MatchController
{
    private ThreeWayMatchService $service;

    public function __construct(ThreeWayMatchService $service)
    {
        $this->service = $service;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $status = $params['status'] ?? null;

        $results = $this->service->list($pagination['offset'], $pagination['per_page'], $status);
        $total = $this->service->count($status);

        $response->getBody()->write(json_encode(['success' => true, 'data' => [
            'match_results' => $results,
            'pagination' => ['page' => $pagination['page'], 'per_page' => $pagination['per_page'], 'total' => $total, 'total_pages' => (int)ceil($total / $pagination['per_page'])],
        ]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $result = $this->service->get($id);
        if (!$result) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'Match result not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['match_result' => $result]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function runMatch(Request $request, Response $response, array $args): Response
    {
        $invoiceId = InputValidator::validateId($args['invoice_id']);
        $userId = (int)$request->getAttribute('user_id');

        $result = $this->service->runMatch($invoiceId, $userId);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['match_result' => $result]]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
