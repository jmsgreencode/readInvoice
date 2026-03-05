<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\EmailIngestionService;
use App\Repository\EmailRepository;
use App\Security\InputValidator;
use App\Exception\AppException;
use App\Exception\ValidationException;

class EmailController
{
    private EmailIngestionService $ingestionService;
    private EmailRepository $emailRepo;

    public function __construct(EmailIngestionService $ingestionService, EmailRepository $emailRepo)
    {
        $this->ingestionService = $ingestionService;
        $this->emailRepo = $emailRepo;
    }

    public function ingest(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $this->validateIngestPayload($body);

        $userId = $request->getAttribute('user_id');
        $requestId = $request->getAttribute('request_id');

        $result = $this->ingestionService->ingest($body, $userId, $requestId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $result,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);

        $vendorId = isset($params['vendor_id']) ? InputValidator::validateId($params['vendor_id'], 'vendor_id') : null;
        $status = $params['status'] ?? null;

        $emails = $this->emailRepo->findAll($pagination['offset'], $pagination['per_page'], $vendorId, $status);
        $total = $this->emailRepo->count($vendorId, $status);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'emails' => $emails,
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
        $email = $this->emailRepo->findById($id);

        if (!$email) {
            throw new AppException('Email not found', 'NOT_FOUND', 404, 'Email not found.');
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['email' => $email],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function validateIngestPayload(array $body): void
    {
        $errors = [];

        if (empty($body['outlook_msg_id'])) {
            $errors['outlook_msg_id'] = 'Required';
        }
        if (empty($body['from_address']) || !filter_var($body['from_address'], FILTER_VALIDATE_EMAIL)) {
            $errors['from_address'] = 'Valid email address required';
        }
        if (empty($body['received_at'])) {
            $errors['received_at'] = 'Required';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
