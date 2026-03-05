<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ComplianceService;
use App\Security\InputValidator;

class ComplianceController
{
    private ComplianceService $service;

    public function __construct(ComplianceService $service)
    {
        $this->service = $service;
    }

    public function listAlerts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $type = $params['type'] ?? null;
        $resolved = isset($params['resolved']) ? (bool)$params['resolved'] : null;

        $alerts = $this->service->listAlerts($pagination['offset'], $pagination['per_page'], $type, $resolved);
        $total = $this->service->countAlerts($type, $resolved);

        $response->getBody()->write(json_encode(['success' => true, 'data' => [
            'alerts' => $alerts,
            'pagination' => ['page' => $pagination['page'], 'per_page' => $pagination['per_page'], 'total' => $total, 'total_pages' => (int)ceil($total / $pagination['per_page'])],
        ]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getSettings(Request $request, Response $response): Response
    {
        $settings = $this->service->getSettings();
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['settings' => $settings]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateSettings(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $userId = (int)$request->getAttribute('user_id');

        foreach ($body as $key => $value) {
            $this->service->updateSetting($key, (string)$value, $userId);
        }

        $settings = $this->service->getSettings();
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['settings' => $settings]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function resolveAlert(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $userId = (int)$request->getAttribute('user_id');
        $this->service->resolveAlert($id, $userId);

        $response->getBody()->write(json_encode(['success' => true, 'data' => null]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
