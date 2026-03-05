<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\VendorRequestService;
use App\Security\InputValidator;

class VendorRequestController
{
    private VendorRequestService $service;

    public function __construct(VendorRequestService $service)
    {
        $this->service = $service;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $status = $params['status'] ?? null;

        // Requestors see only their own; admins see all
        $permissions = $request->getAttribute('permissions', []);
        $requestedBy = in_array('vendor_requests.view_all', $permissions) ? null : (int)$request->getAttribute('user_id');

        $items = $this->service->list($pagination['offset'], $pagination['per_page'], $status, $requestedBy);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['vendor_requests' => $items]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $item = $this->service->get($id);
        if (!$item) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'Vendor request not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['vendor_request' => $item]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $userId = (int)$request->getAttribute('user_id');

        $data = [
            'requested_by' => $userId,
            'vendor_name' => InputValidator::validateString($body['vendor_name'] ?? '', 'vendor_name', 1, 255),
            'vendor_website' => $body['vendor_website'] ?? null,
            'vendor_contact_name' => $body['vendor_contact_name'] ?? null,
            'vendor_contact_email' => $body['vendor_contact_email'] ?? null,
            'vendor_contact_phone' => $body['vendor_contact_phone'] ?? null,
            'business_justification' => InputValidator::validateString($body['business_justification'] ?? '', 'business_justification', 1, 5000),
            'category' => $body['category'] ?? null,
        ];

        $id = $this->service->create($data);
        $item = $this->service->get($id);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['vendor_request' => $item]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $body = $request->getParsedBody() ?? [];
        $this->service->update($id, $body);
        $item = $this->service->get($id);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['vendor_request' => $item]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function submit(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $this->service->submit($id);
        $response->getBody()->write(json_encode(['success' => true, 'data' => null]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function review(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $userId = (int)$request->getAttribute('user_id');
        $body = $request->getParsedBody() ?? [];
        $decision = $body['decision'] ?? 'reject';
        $notes = $body['notes'] ?? null;

        $this->service->review($id, $userId, $decision, $notes);
        $response->getBody()->write(json_encode(['success' => true, 'data' => null]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function promote(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $userId = (int)$request->getAttribute('user_id');

        $vendorId = $this->service->promote($id, $userId);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['vendor_id' => $vendorId]]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
