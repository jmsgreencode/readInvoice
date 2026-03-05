<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\GoodsReceivedNoteService;
use App\Security\InputValidator;

class GoodsReceivedNoteController
{
    private GoodsReceivedNoteService $service;

    public function __construct(GoodsReceivedNoteService $service)
    {
        $this->service = $service;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $poId = isset($params['po_id']) ? (int)$params['po_id'] : null;

        $grns = $this->service->list($pagination['offset'], $pagination['per_page'], $poId);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['grns' => $grns]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $grn = $this->service->get($id);
        if (!$grn) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'GRN not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['grn' => $grn]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $userId = (int)$request->getAttribute('user_id');

        $grnId = $this->service->create(
            (int)($body['po_id'] ?? 0),
            $userId,
            $body['received_date'] ?? date('Y-m-d'),
            $body['line_items'] ?? [],
            $body['notes'] ?? null
        );

        $grn = $this->service->get($grnId);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['grn' => $grn]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
}
