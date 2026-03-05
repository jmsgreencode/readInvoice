<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\DepartmentService;
use App\Security\InputValidator;

class DepartmentController
{
    private DepartmentService $service;

    public function __construct(DepartmentService $service)
    {
        $this->service = $service;
    }

    public function list(Request $request, Response $response): Response
    {
        $departments = $this->service->list();
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['departments' => $departments]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $dept = $this->service->get($id);
        if (!$dept) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'Department not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['department' => $dept]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $name = InputValidator::validateString($body['name'] ?? '', 'name', 1, 255);
        $code = InputValidator::validateString($body['code'] ?? '', 'code', 1, 20);
        $managerId = isset($body['manager_user_id']) ? (int)$body['manager_user_id'] : null;

        $id = $this->service->create($name, $code, $managerId);
        $dept = $this->service->get($id);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['department' => $dept]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];
        $name = InputValidator::validateString($body['name'] ?? '', 'name', 1, 255);
        $code = InputValidator::validateString($body['code'] ?? '', 'code', 1, 20);
        $managerId = isset($body['manager_user_id']) ? (int)$body['manager_user_id'] : null;
        $isActive = (bool)($body['is_active'] ?? true);

        $this->service->update($id, $name, $code, $managerId, $isActive);
        $dept = $this->service->get($id);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['department' => $dept]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $this->service->delete($id);
        $response->getBody()->write(json_encode(['success' => true, 'data' => null]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(204);
    }
}
