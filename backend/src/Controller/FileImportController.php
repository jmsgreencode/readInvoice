<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\FileImportService;
use App\Security\InputValidator;

class FileImportController
{
    private FileImportService $service;

    public function __construct(FileImportService $service)
    {
        $this->service = $service;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $imports = $this->service->list($pagination['offset'], $pagination['per_page']);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['imports' => $imports]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $import = $this->service->get($id);
        if (!$import) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'Import not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['import' => $import]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'No file uploaded']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $userId = (int)$request->getAttribute('user_id');
        $body = $request->getParsedBody() ?? [];
        $importType = $body['import_type'] ?? 'vendors';

        $tempPath = tempnam(sys_get_temp_dir(), 'import_');
        $file->moveTo($tempPath);

        $importId = $this->service->processUpload($tempPath, $file->getClientFilename(), $importType, $userId);
        @unlink($tempPath);

        $import = $this->service->get($importId);
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['import' => $import]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function retry(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $this->service->retry($id);
        $response->getBody()->write(json_encode(['success' => true, 'data' => null]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
