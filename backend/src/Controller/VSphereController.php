<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\VSphereService;
use App\Exception\AppException;

class VSphereController
{
    private VSphereService $vsphereService;

    public function __construct(VSphereService $vsphereService)
    {
        $this->vsphereService = $vsphereService;
    }

    public function listVMs(Request $request, Response $response): Response
    {
        $this->requireAdmin($request);

        $filters = $request->getQueryParams();
        $vms = $this->vsphereService->listVMs($filters);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['vms' => $vms],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function listDatastores(Request $request, Response $response): Response
    {
        $this->requireAdmin($request);

        $datastores = $this->vsphereService->listDatastores();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['datastores' => $datastores],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function vmPowerAction(Request $request, Response $response, array $args): Response
    {
        $this->requireAdmin($request);

        $vmId = $args['id'] ?? '';
        $body = $request->getParsedBody() ?? [];
        $action = $body['action'] ?? '';

        $this->vsphereService->vmPowerAction($vmId, $action);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['message' => "VM {$action} action initiated"],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function requireAdmin(Request $request): void
    {
        $role = $request->getAttribute('user_role', 'user');
        if ($role !== 'admin') {
            throw new AppException(
                'Admin access required for vSphere operations',
                'FORBIDDEN',
                403,
                'You do not have permission to perform this action.'
            );
        }
    }
}
