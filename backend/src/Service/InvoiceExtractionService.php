<?php

declare(strict_types=1);

namespace App\Service;

use App\Logging\StructuredLogger;
use Smalot\PdfParser\Parser as PdfParser;

class InvoiceExtractionService
{
    private StructuredLogger $logger;
    private string $tesseractLang;

    public function __construct(StructuredLogger $logger, string $tesseractLang = 'eng')
    {
        $this->logger = $logger;
        $this->tesseractLang = $tesseractLang;
    }

    /**
     * Extract text and invoice data from a PDF file.
     *
     * @return array{raw_text: string, data: array, confidence: float}
     */
    public function extract(string $pdfPath): array
    {
        $text = $this->extractTextFromPdf($pdfPath);

        // Fall back to OCR if text extraction yields too little content
        if (strlen(trim($text)) < 50) {
            $this->logger->info('PDF text extraction insufficient, falling back to OCR', [
                'pdf' => basename($pdfPath),
                'text_length' => strlen($text),
            ]);
            $text = $this->extractWithOcr($pdfPath);
        }

        $data = $this->parseInvoiceFields($text);
        $confidence = $this->calculateConfidence($data);

        $this->logger->info('Invoice extraction completed', [
            'pdf' => basename($pdfPath),
            'confidence' => $confidence,
            'fields_found' => array_keys(array_filter($data)),
        ]);

        return [
            'raw_text' => $text,
            'data' => $data,
            'confidence' => $confidence,
        ];
    }

    private function extractTextFromPdf(string $pdfPath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($pdfPath);
            return $pdf->getText();
        } catch (\Exception $e) {
            $this->logger->warning('PDF text extraction failed', [
                'pdf' => basename($pdfPath),
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function extractWithOcr(string $pdfPath): string
    {
        $outputBase = tempnam(sys_get_temp_dir(), 'ocr_');
        $outputFile = $outputBase . '.txt';

        $escapedPath = escapeshellarg($pdfPath);
        $escapedOutput = escapeshellarg($outputBase);
        $escapedLang = escapeshellarg($this->tesseractLang);

        $command = "tesseract {$escapedPath} {$escapedOutput} -l {$escapedLang} 2>&1";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            @unlink($outputFile);
            @unlink($outputBase);

            $this->logger->error('Tesseract OCR failed', [
                'pdf' => basename($pdfPath),
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
            ]);

            throw new \RuntimeException('OCR processing failed');
        }

        $text = file_get_contents($outputFile);
        @unlink($outputFile);
        @unlink($outputBase);

        return trim($text ?: '');
    }

    public function parseInvoiceFields(string $text): array
    {
        $data = [
            'invoice_number' => null,
            'invoice_date' => null,
            'due_date' => null,
            'total_amount' => null,
            'currency' => 'USD',
            'vendor_name' => null,
        ];

        // Invoice number patterns
        $invoicePatterns = [
            '/invoice\s*(?:#|no\.?|number)\s*:?\s*([A-Z0-9][\w\-\/]+)/i',
            '/inv[#\-\s]*:?\s*([A-Z0-9][\w\-\/]+)/i',
            '/(?:bill|receipt)\s*(?:#|no\.?|number)\s*:?\s*([A-Z0-9][\w\-\/]+)/i',
        ];
        foreach ($invoicePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data['invoice_number'] = trim($matches[1]);
                break;
            }
        }

        // Date patterns
        $datePatterns = [
            '/(?:invoice\s+)?date\s*:?\s*(\d{1,2}[\\/\-]\d{1,2}[\\/\-]\d{2,4})/i',
            '/(?:invoice\s+)?date\s*:?\s*(\d{4}[\\/\-]\d{1,2}[\\/\-]\d{1,2})/i',
            '/(?:invoice\s+)?date\s*:?\s*(\w+ \d{1,2},?\s*\d{4})/i',
        ];
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $ts = strtotime($matches[1]);
                if ($ts !== false) {
                    $data['invoice_date'] = date('Y-m-d', $ts);
                    break;
                }
            }
        }

        // Due date
        $dueDatePatterns = [
            '/due\s+date\s*:?\s*(\d{1,2}[\\/\-]\d{1,2}[\\/\-]\d{2,4})/i',
            '/due\s+date\s*:?\s*(\d{4}[\\/\-]\d{1,2}[\\/\-]\d{1,2})/i',
            '/due\s+date\s*:?\s*(\w+ \d{1,2},?\s*\d{4})/i',
            '/payment\s+due\s*:?\s*(\d{1,2}[\\/\-]\d{1,2}[\\/\-]\d{2,4})/i',
        ];
        foreach ($dueDatePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $ts = strtotime($matches[1]);
                if ($ts !== false) {
                    $data['due_date'] = date('Y-m-d', $ts);
                    break;
                }
            }
        }

        // Total amount
        $amountPatterns = [
            '/(?:total|amount\s+due|balance\s+due|grand\s+total)\s*:?\s*\$?\s*([\d,]+\.?\d{0,2})/i',
            '/(?:total|amount\s+due)\s*:?\s*(?:USD|EUR|GBP)?\s*([\d,]+\.?\d{0,2})/i',
        ];
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data['total_amount'] = (float)str_replace(',', '', $matches[1]);
                break;
            }
        }

        // Currency detection
        if (preg_match('/€/', $text) || preg_match('/EUR/i', $text)) {
            $data['currency'] = 'EUR';
        } elseif (preg_match('/£/', $text) || preg_match('/GBP/i', $text)) {
            $data['currency'] = 'GBP';
        }

        // Vendor name: first non-empty, non-numeric line
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        foreach ($lines as $line) {
            if (strlen($line) > 2 && !preg_match('/^\d+$/', $line) && !preg_match('/^(invoice|date|bill|page)/i', $line)) {
                $data['vendor_name'] = substr($line, 0, 255);
                break;
            }
        }

        return $data;
    }

    private function calculateConfidence(array $data): float
    {
        $score = 0;
        $maxScore = 6;

        if ($data['invoice_number'] !== null) $score += 1.5;
        if ($data['invoice_date'] !== null) $score += 1.0;
        if ($data['due_date'] !== null) $score += 0.5;
        if ($data['total_amount'] !== null) $score += 1.5;
        if ($data['vendor_name'] !== null) $score += 1.0;
        if ($data['currency'] !== 'USD') $score += 0.5; // Explicit currency found

        return round(($score / $maxScore) * 100, 2);
    }
}
