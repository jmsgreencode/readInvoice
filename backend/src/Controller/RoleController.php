<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\RbacService;
use App\Security\InputValidator;

class RoleController
{
    private RbacService $rbacService;

    public function __construct(RbacService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    public function list(Request $request, Response $response): Response
    {
        $roles = $this->rbacService->listRoles();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['roles' => $roles],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $role = $this->rbacService->getRole($id);

        if (!$role) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'Role not found'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['role' => $role],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $name = InputValidator::validateString($body['name'] ?? '', 'name', 1, 100);
        $displayName = InputValidator::validateString($body['display_name'] ?? '', 'display_name', 1, 255);
        $description = isset($body['description']) ? InputValidator::validateString($body['description'], 'description', 0, 1000) : null;

        $id = $this->rbacService->createRole($name, $displayName, $description);
        $role = $this->rbacService->getRole($id);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['role' => $role],
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];
        $name = InputValidator::validateString($body['name'] ?? '', 'name', 1, 100);
        $displayName = InputValidator::validateString($body['display_name'] ?? '', 'display_name', 1, 255);
        $description = isset($body['description']) ? InputValidator::validateString($body['description'], 'description', 0, 1000) : null;

        $this->rbacService->updateRole($id, $name, $displayName, $description);
        $role = $this->rbacService->getRole($id);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['role' => $role],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $this->rbacService->deleteRole($id);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => null,
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(204);
    }

    public function getPermissions(Request $request, Response $response, array $args): Response
    {
        $roleId = InputValidator::validateId($args['id']);
        $role = $this->rbacService->getRole($roleId);

        if (!$role) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'Role not found'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['permissions' => $role['permissions']],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function setPermissions(Request $request, Response $response, array $args): Response
    {
        $roleId = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];
        $permissionIds = $body['permission_ids'] ?? [];

        if (!is_array($permissionIds)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'permission_ids must be an array'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $this->rbacService->setRolePermissions($roleId, $permissionIds);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => null,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function listAllPermissions(Request $request, Response $response): Response
    {
        $permissions = $this->rbacService->listPermissions();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['permissions' => $permissions],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
