#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Logging\StructuredLogger;

if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
}

$dbConfig = require __DIR__ . '/../config/database.php';
$logger = new StructuredLogger('seeder');

try {
    $db = new Connection($dbConfig['dsn'], $dbConfig['user'], $dbConfig['pass'], $logger);
} catch (\Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Seeding dummy data...\n";

// -- Vendors --
$vendors = [
    ['Acme Corporation', 'acme.com', 'billing@acme.com'],
    ['TechSupply Inc', 'techsupply.io', 'invoices@techsupply.io'],
    ['CloudHost Services', 'cloudhost.net', 'accounts@cloudhost.net'],
    ['Office Essentials Ltd', 'officeessentials.com', 'ap@officeessentials.com'],
    ['DataPipe Analytics', 'datapipe.co', 'finance@datapipe.co'],
    ['SecureNet Solutions', 'securenet.com', 'billing@securenet.com'],
    ['PrintWorks Global', 'printworks.com', 'invoicing@printworks.com'],
];

$vendorIds = [];
foreach ($vendors as [$name, $domain, $email]) {
    $db->execute(
        'INSERT IGNORE INTO vendors (name, domain, contact_email) VALUES (?, ?, ?)',
        [$name, $domain, $email]
    );
    $stmt = $db->execute('SELECT id FROM vendors WHERE domain = ?', [$domain]);
    $vendorIds[$domain] = (int)$stmt->fetch()['id'];
}
echo "  Vendors: " . count($vendorIds) . "\n";

// -- Emails --
$emails = [
    ['acme.com', 'billing@acme.com', 'Acme Billing', 'Invoice #INV-2024-0042 for January Services', '2024-01-15 09:30:00', 'Please find attached your invoice for consulting services rendered in January 2024. Total amount due: $9,396.00. Payment terms: Net 30.', true, true],
    ['acme.com', 'billing@acme.com', 'Acme Billing', 'Invoice #INV-2024-0078 - February Maintenance', '2024-02-12 14:22:00', 'Attached is your February maintenance invoice. Amount: $3,200.00. Due date: March 14, 2024.', true, true],
    ['acme.com', 'sales@acme.com', 'Acme Sales', 'New product catalog Q2 2024', '2024-02-20 11:00:00', 'Hi, please review our updated Q2 product catalog. Let us know if you need any quotes.', true, false],
    ['techsupply.io', 'invoices@techsupply.io', 'TechSupply Accounts', 'Payment Due - Invoice TS-8891', '2024-01-22 08:15:00', 'Your invoice TS-8891 for hardware procurement is attached. Total: $15,750.00. Payment due: February 22, 2024.', true, true],
    ['techsupply.io', 'invoices@techsupply.io', 'TechSupply Accounts', 'Invoice TS-9002 - Laptop Order', '2024-03-01 10:45:00', 'Invoice for 10x ThinkPad T14 laptops. Amount: $12,500.00. Net 30 payment terms.', true, true],
    ['techsupply.io', 'support@techsupply.io', 'TechSupply Support', 'RE: Warranty claim #WC-445', '2024-02-28 16:30:00', 'Your warranty claim has been approved. Replacement unit will ship within 5 business days.', false, false],
    ['cloudhost.net', 'accounts@cloudhost.net', 'CloudHost Finance', 'Monthly Invoice - March 2024', '2024-03-01 00:05:00', 'Your monthly cloud hosting invoice is ready. Total: $4,850.00 for 12 instances, 5TB storage, 2TB bandwidth.', true, true],
    ['cloudhost.net', 'accounts@cloudhost.net', 'CloudHost Finance', 'Monthly Invoice - February 2024', '2024-02-01 00:05:00', 'Your monthly cloud hosting invoice. Total: $4,200.00.', true, true],
    ['cloudhost.net', 'noreply@cloudhost.net', 'CloudHost Alerts', 'Usage alert: 80% bandwidth threshold', '2024-02-15 22:00:00', 'Your account has reached 80% of its monthly bandwidth allocation.', false, false],
    ['officeessentials.com', 'ap@officeessentials.com', 'Office Essentials', 'Invoice OE-2024-156 Supplies Order', '2024-01-30 13:20:00', 'Invoice for office supplies order. Pens, paper, toner cartridges. Total: $892.50.', true, true],
    ['officeessentials.com', 'ap@officeessentials.com', 'Office Essentials', 'Invoice OE-2024-203 Furniture Order', '2024-02-18 09:00:00', 'Standing desks (4x) and ergonomic chairs (4x). Total: $6,400.00. Delivery scheduled March 5.', true, true],
    ['datapipe.co', 'finance@datapipe.co', 'DataPipe Finance', 'Q1 2024 Analytics Platform License', '2024-01-05 07:00:00', 'Quarterly license renewal for DataPipe Analytics Platform - Enterprise tier. Amount: $22,500.00.', true, true],
    ['datapipe.co', 'finance@datapipe.co', 'DataPipe Finance', 'Statement of Account - February 2024', '2024-02-28 17:00:00', 'Your February statement is attached. Outstanding balance: $22,500.00.', true, true],
    ['securenet.com', 'billing@securenet.com', 'SecureNet Billing', 'Annual Security Audit - Invoice SN-4401', '2024-02-01 11:30:00', 'Annual penetration testing and security audit. Total: $18,000.00. Payment due within 45 days.', true, true],
    ['securenet.com', 'alerts@securenet.com', 'SecureNet SOC', 'Weekly Threat Report - Week 8', '2024-02-19 06:00:00', 'Your weekly security threat report is attached. No critical incidents detected.', true, false],
    ['printworks.com', 'invoicing@printworks.com', 'PrintWorks', 'Invoice PW-7823 - Marketing Materials', '2024-02-10 15:45:00', 'Brochures (5000x), business cards (2000x), banners (10x). Total: $3,175.00.', true, true],
    ['printworks.com', 'invoicing@printworks.com', 'PrintWorks', 'Invoice PW-7901 - Annual Report Print', '2024-03-02 08:30:00', 'Annual report printing - 500 copies, hardbound. Total: $7,250.00.', true, true],
];

$emailIds = [];
foreach ($emails as $i => [$domain, $from, $fromName, $subject, $receivedAt, $body, $hasAttach, $hasInvoice]) {
    $vendorId = $vendorIds[$domain];
    $msgId = 'AAMk' . str_pad((string)($i + 1), 8, '0', STR_PAD_LEFT) . '@outlook.com';
    $status = $hasInvoice ? 'completed' : 'completed';

    $db->execute(
        'INSERT IGNORE INTO emails (vendor_id, outlook_msg_id, from_address, from_name, subject, received_at, body_preview, has_attachments, has_invoice, processing_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$vendorId, $msgId, $from, $fromName, $subject, $receivedAt, $body, $hasAttach ? 1 : 0, $hasInvoice ? 1 : 0, $status]
    );
    $stmt = $db->execute('SELECT id FROM emails WHERE outlook_msg_id = ?', [$msgId]);
    $emailIds[] = (int)$stmt->fetch()['id'];
}
echo "  Emails: " . count($emailIds) . "\n";

// -- Invoices (for emails that have invoices) --
$invoices = [
    [0, 'acme.com', 'INV-2024-0042', '2024-01-15', '2024-02-14', 9396.00, 'USD', 'January_Invoice_Acme.pdf', 'completed', 85.5],
    [1, 'acme.com', 'INV-2024-0078', '2024-02-12', '2024-03-14', 3200.00, 'USD', 'February_Maintenance_Acme.pdf', 'completed', 92.0],
    [3, 'techsupply.io', 'TS-8891', '2024-01-22', '2024-02-22', 15750.00, 'USD', 'Invoice_TS-8891.pdf', 'completed', 88.3],
    [4, 'techsupply.io', 'TS-9002', '2024-03-01', '2024-03-31', 12500.00, 'USD', 'Invoice_TS-9002_Laptops.pdf', 'completed', 79.1],
    [6, 'cloudhost.net', 'CH-2024-03', '2024-03-01', '2024-03-31', 4850.00, 'USD', 'CloudHost_March_2024.pdf', 'completed', 95.0],
    [7, 'cloudhost.net', 'CH-2024-02', '2024-02-01', '2024-02-29', 4200.00, 'USD', 'CloudHost_Feb_2024.pdf', 'completed', 94.2],
    [9, 'officeessentials.com', 'OE-2024-156', '2024-01-30', '2024-02-28', 892.50, 'USD', 'OE_Supplies_Invoice.pdf', 'completed', 76.8],
    [10, 'officeessentials.com', 'OE-2024-203', '2024-02-18', '2024-03-20', 6400.00, 'USD', 'OE_Furniture_Invoice.pdf', 'manual_review', 62.3],
    [11, 'datapipe.co', 'DP-Q1-2024', '2024-01-05', '2024-02-04', 22500.00, 'USD', 'DataPipe_Q1_License.pdf', 'completed', 91.5],
    [12, 'datapipe.co', 'DP-STMT-0224', '2024-02-28', null, 22500.00, 'USD', 'DataPipe_Statement_Feb.pdf', 'manual_review', 55.0],
    [13, 'securenet.com', 'SN-4401', '2024-02-01', '2024-03-17', 18000.00, 'USD', 'SecureNet_Audit_Invoice.pdf', 'completed', 87.9],
    [15, 'printworks.com', 'PW-7823', '2024-02-10', '2024-03-12', 3175.00, 'USD', 'PrintWorks_Marketing.pdf', 'completed', 83.4],
    [16, 'printworks.com', 'PW-7901', '2024-03-02', '2024-04-01', 7250.00, 'USD', 'PrintWorks_AnnualReport.pdf', 'pending', 0.0],
];

$invoiceCount = 0;
foreach ($invoices as [$emailIdx, $domain, $invNumber, $invDate, $dueDate, $amount, $currency, $pdfName, $status, $confidence]) {
    $emailId = $emailIds[$emailIdx];
    $vendorId = $vendorIds[$domain];
    $sha256 = hash('sha256', $pdfName . $invNumber);
    $pdfPath = "{$vendorId}/{$invDate}/{$sha256}.pdf";

    $ocrText = "INVOICE\n\nVendor: " . array_search($domain, array_column($vendors, 1))
        . "\nInvoice Number: {$invNumber}\nDate: {$invDate}"
        . ($dueDate ? "\nDue Date: {$dueDate}" : '')
        . "\n\nTotal Amount: \${$amount}\n\nThank you for your business.";

    $db->execute(
        'INSERT IGNORE INTO invoices (email_id, vendor_id, invoice_number, invoice_date, due_date, total_amount, currency, pdf_path, pdf_sha256, pdf_original_name, ocr_raw_text, extraction_confidence, extraction_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$emailId, $vendorId, $invNumber, $invDate, $dueDate, $amount, $currency, $pdfPath, $sha256, $pdfName, $ocrText, $confidence, $status]
    );
    $invoiceCount++;
}
echo "  Invoices: {$invoiceCount}\n";

// -- Audit Logs --
$db->execute(
    'INSERT INTO audit_logs (user_id, action, entity_type, detail_json, request_id) VALUES (?, ?, ?, ?, ?)',
    [1, 'system.seed', 'system', json_encode(['message' => 'Database seeded with demo data']), 'seed-' . date('Ymd-His')]
);

echo "\nDone! Login with:\n  Username: admin\n  Password: admin123\n";
