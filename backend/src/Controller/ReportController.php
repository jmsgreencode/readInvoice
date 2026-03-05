<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ReportService;
use App\Service\ExportService;

class ReportController
{
    private ReportService $reportService;
    private ExportService $exportService;

    public function __construct(ReportService $reportService, ExportService $exportService)
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    public function available(Request $request, Response $response): Response
    {
        $reports = $this->reportService->getAvailableReports();
        $response->getBody()->write(json_encode(['success' => true, 'data' => ['reports' => $reports]]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getReport(Request $request, Response $response, array $args): Response
    {
        $type = $args['type'];
        $filters = $request->getQueryParams();
        $data = $this->reportService->getReport($type, $filters);

        $response->getBody()->write(json_encode(['success' => true, 'data' => $data]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function exportReport(Request $request, Response $response, array $args): Response
    {
        $type = $args['type'];
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'csv';
        $filters = array_diff_key($params, ['format' => true]);

        $reportData = $this->reportService->getReport($type, $filters);
        $title = $this->reportService->getAvailableReports()[$type]['name'] ?? $type;

        switch ($format) {
            case 'xlsx':
                $content = $this->exportService->exportToXlsx($reportData, $title);
                $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                $ext = 'xlsx';
                break;
            case 'pdf':
                $content = $this->exportService->exportToPdf($reportData, $title);
                $contentType = 'application/pdf';
                $ext = 'pdf';
                break;
            default:
                $content = $this->exportService->exportToCsv($reportData);
                $contentType = 'text/csv';
                $ext = 'csv';
        }

        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Disposition', "attachment; filename=\"{$type}_report.{$ext}\"");
    }
}
