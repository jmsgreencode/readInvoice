<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\InvoiceExtractionService;
use App\Logging\StructuredLogger;
use Mockery;

class InvoiceExtractionServiceTest extends TestCase
{
    private InvoiceExtractionService $service;

    protected function setUp(): void
    {
        $logger = Mockery::mock(StructuredLogger::class)->shouldIgnoreMissing();
        $this->service = new InvoiceExtractionService($logger, 'eng');
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testParseInvoiceNumber(): void
    {
        $text = "ACME Corp\nInvoice #INV-2024-001\nDate: 01/15/2024\nTotal: $1,500.00";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('INV-2024-001', $result['invoice_number']);
    }

    public function testParseInvoiceNumberVariant(): void
    {
        $text = "Company LLC\nInvoice Number: 98765\nDate: 2024-03-01";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('98765', $result['invoice_number']);
    }

    public function testParseDate(): void
    {
        $text = "Invoice\nDate: 01/15/2024\nDue Date: 02/15/2024\nTotal: $500.00";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('2024-01-15', $result['invoice_date']);
        $this->assertEquals('2024-02-15', $result['due_date']);
    }

    public function testParseDateIsoFormat(): void
    {
        $text = "Invoice\nDate: 2024-03-01\nTotal: $100.00";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('2024-03-01', $result['invoice_date']);
    }

    public function testParseTotalAmount(): void
    {
        $text = "Items\nSubtotal: $1,000.00\nTax: $80.00\nTotal: $1,080.00";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals(1080.00, $result['total_amount']);
    }

    public function testParseAmountDue(): void
    {
        $text = "Balance\nAmount Due: $2,500.50";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals(2500.50, $result['total_amount']);
    }

    public function testDetectEurCurrency(): void
    {
        $text = "Invoice\nTotal: €500.00";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('EUR', $result['currency']);
    }

    public function testDetectGbpCurrency(): void
    {
        $text = "Invoice\nTotal: £250.00";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('GBP', $result['currency']);
    }

    public function testDefaultCurrencyUsd(): void
    {
        $text = "Invoice\nTotal: $100.00";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('USD', $result['currency']);
    }

    public function testParseVendorName(): void
    {
        $text = "ACME Corporation\n123 Business St\nInvoice #001";
        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('ACME Corporation', $result['vendor_name']);
    }

    public function testEmptyTextReturnsDefaults(): void
    {
        $result = $this->service->parseInvoiceFields('');

        $this->assertNull($result['invoice_number']);
        $this->assertNull($result['invoice_date']);
        $this->assertNull($result['due_date']);
        $this->assertNull($result['total_amount']);
        $this->assertEquals('USD', $result['currency']);
    }

    public function testFullInvoiceParsing(): void
    {
        $text = <<<TEXT
        ACME Industries
        123 Main Street, Suite 100
        New York, NY 10001

        Invoice No: INV-2024-0042
        Invoice Date: March 15, 2024
        Due Date: 04/15/2024

        Description                    Amount
        Consulting Services         $5,000.00
        Software License            $2,500.00
        Support (Annual)            $1,200.00

        Subtotal:                   $8,700.00
        Tax (8%):                     $696.00
        Total:                      $9,396.00

        Payment Due: $9,396.00
        TEXT;

        $result = $this->service->parseInvoiceFields($text);

        $this->assertEquals('INV-2024-0042', $result['invoice_number']);
        $this->assertNotNull($result['invoice_date']);
        $this->assertNotNull($result['total_amount']);
        $this->assertEquals('USD', $result['currency']);
    }
}
