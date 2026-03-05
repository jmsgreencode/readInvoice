<?php

declare(strict_types=1);

namespace App\Service;

use App\Logging\StructuredLogger;

class CsvImportService
{
    private StructuredLogger $logger;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Parse a CSV file and return rows as associative arrays.
     */
    public function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \RuntimeException('CSV file has no header row');
        }

        // Normalize headers
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $rows = [];
        $lineNum = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $lineNum++;
            if (count($data) !== count($headers)) {
                $this->logger->warning('CSV row column mismatch', ['line' => $lineNum, 'expected' => count($headers), 'got' => count($data)]);
                continue;
            }
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Map CSV column names to expected field names.
     */
    public function mapColumns(array $rows, array $columnMapping): array
    {
        return array_map(function ($row) use ($columnMapping) {
            $mapped = [];
            foreach ($columnMapping as $targetField => $sourceColumn) {
                $mapped[$targetField] = $row[$sourceColumn] ?? null;
            }
            return $mapped;
        }, $rows);
    }
}
