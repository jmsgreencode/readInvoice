<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\RbacService;
use App\Repository\UserRoleRepository;
use App\Security\InputValidator;

class UserController
{
    private RbacService $rbacService;
    private UserRoleRepository $userRoleRepo;

    public function __construct(RbacService $rbacService, UserRoleRepository $userRoleRepo)
    {
        $this->rbacService = $rbacService;
        $this->userRoleRepo = $userRoleRepo;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $search = isset($params['search']) ? trim($params['search']) : null;

        $users = $this->userRoleRepo->findAllUsers($pagination['offset'], $pagination['per_page'], $search);
        $total = $this->userRoleRepo->countUsers($search);

        // Attach roles to each user
        foreach ($users as &$user) {
            $user['roles'] = $this->rbacService->getUserRoles((int)$user['id']);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'users' => $users,
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

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $user = $this->userRoleRepo->findUserById($id);

        if (!$user) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'User not found'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $user['roles'] = $this->rbacService->getUserRoles($id);
        $user['permissions'] = $this->rbacService->getUserPermissions($id);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['user' => $user],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];

        $data = [];
        if (isset($body['display_name'])) {
            $data['display_name'] = InputValidator::validateString($body['display_name'], 'display_name', 0, 255);
        }
        if (isset($body['email'])) {
            $data['email'] = $body['email'] ? InputValidator::validateEmail($body['email']) : null;
        }
        if (isset($body['department_id'])) {
            $data['department_id'] = $body['department_id'] ? (int)$body['department_id'] : null;
        }
        if (isset($body['is_active'])) {
            $data['is_active'] = (int)(bool)$body['is_active'];
        }

        $this->userRoleRepo->updateUser($id, $data);
        $user = $this->userRoleRepo->findUserById($id);
        $user['roles'] = $this->rbacService->getUserRoles($id);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['user' => $user],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function assignRole(Request $request, Response $response, array $args): Response
    {
        $userId = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];
        $roleId = (int)($body['role_id'] ?? 0);

        if ($roleId <= 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => ['message' => 'role_id is required'],
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $assignedBy = (int)$request->getAttribute('user_id');
        $this->rbacService->assignRole($userId, $roleId, $assignedBy);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['roles' => $this->rbacService->getUserRoles($userId)],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function removeRole(Request $request, Response $response, array $args): Response
    {
        $userId = InputValidator::validateId($args['id']);
        $roleId = InputValidator::validateId($args['role_id']);

        $this->rbacService->removeRole($userId, $roleId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['roles' => $this->rbacService->getUserRoles($userId)],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
