<?php

declare(strict_types=1);

namespace App\Service;

use App\Logging\StructuredLogger;

class ExportService
{
    private StructuredLogger $logger;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
    }

    public function exportToCsv(array $reportData): string
    {
        $output = fopen('php://temp', 'r+');

        // Write header
        fputcsv($output, $reportData['columns']);

        // Write rows
        foreach ($reportData['rows'] as $row) {
            fputcsv($output, array_values($row));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    public function exportToXlsx(array $reportData, string $title): string
    {
        // PhpSpreadsheet export - returns file path
        // Requires phpoffice/phpspreadsheet composer package
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            // Fallback to CSV if PhpSpreadsheet not installed
            return $this->exportToCsv($reportData);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31));

        // Headers
        foreach ($reportData['columns'] as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Data rows
        $rowNum = 2;
        foreach ($reportData['rows'] as $row) {
            $col = 1;
            foreach (array_values($row) as $value) {
                $sheet->setCellValueByColumnAndRow($col, $rowNum, $value);
                $col++;
            }
            $rowNum++;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'export_') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    public function exportToPdf(array $reportData, string $title): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            // Fallback to CSV if DomPDF not installed
            return $this->exportToCsv($reportData);
        }

        $html = '<html><head><style>
            body { font-family: Arial, sans-serif; font-size: 10px; }
            h1 { font-size: 16px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
            th { background: #f0f0f0; font-weight: bold; }
        </style></head><body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<table><thead><tr>';

        foreach ($reportData['columns'] as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($reportData['rows'] as $row) {
            $html .= '<tr>';
            foreach (array_values($row) as $value) {
                $html .= '<td>' . htmlspecialchars((string)($value ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
