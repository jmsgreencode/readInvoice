<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\VendorStagingService;
use App\Service\CsvImportService;
use App\Service\FileImportService;
use App\Security\InputValidator;

class VendorStagingController
{
    private VendorStagingService $stagingService;
    private CsvImportService $csvService;
    private FileImportService $importService;

    public function __construct(VendorStagingService $stagingService, CsvImportService $csvService, FileImportService $importService)
    {
        $this->stagingService = $stagingService;
        $this->csvService = $csvService;
        $this->importService = $importService;
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pagination = InputValidator::validatePagination($params);
        $batchId = $params['batch_id'] ?? null;
        $status = $params['status'] ?? null;

        $items = $this->stagingService->list($pagination['offset'], $pagination['per_page'], $batchId, $status);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['staging' => $items]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $item = $this->stagingService->get($id);
        if (!$item) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'Staged vendor not found']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['staging' => $item]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function importCsv(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => ['message' => 'No file uploaded']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $userId = (int)$request->getAttribute('user_id');
        $tempPath = tempnam(sys_get_temp_dir(), 'csv_') . '.csv';
        $file->moveTo($tempPath);

        $importId = $this->importService->processUpload($tempPath, $file->getClientFilename(), 'vendors', $userId);
        @unlink($tempPath);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['import_id' => $importId]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function promote(Request $request, Response $response, array $args): Response
    {
        $id = InputValidator::validateId($args['id']);
        $userId = (int)$request->getAttribute('user_id');

        $vendorId = $this->stagingService->promote($id, $userId);

        $response->getBody()->write(json_encode(['success' => true, 'data' => ['vendor_id' => $vendorId]]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
