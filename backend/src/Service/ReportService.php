<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\Connection;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class ReportService
{
    private Connection $db;
    private StructuredLogger $logger;

    private const AVAILABLE_REPORTS = [
        'vendors' => ['name' => 'Vendor Report', 'description' => 'All vendors with lifecycle status'],
        'requisitions' => ['name' => 'Requisition Report', 'description' => 'Purchase requisitions by status'],
        'purchase_orders' => ['name' => 'Purchase Order Report', 'description' => 'Purchase orders overview'],
        'invoices' => ['name' => 'Invoice Report', 'description' => 'Invoices with extraction status'],
        'budget_utilization' => ['name' => 'Budget Utilization', 'description' => 'Budget allocation and spend'],
        'matching' => ['name' => 'Match Results', 'description' => '3-way match results'],
        'compliance' => ['name' => 'Compliance Alerts', 'description' => 'Active compliance alerts'],
        'audit_trail' => ['name' => 'Audit Trail', 'description' => 'System audit log'],
    ];

    public function __construct(Connection $db, StructuredLogger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function getAvailableReports(): array
    {
        return self::AVAILABLE_REPORTS;
    }

    public function getReport(string $type, array $filters = []): array
    {
        if (!isset(self::AVAILABLE_REPORTS[$type])) {
            throw new AppException('Unknown report type', 'NOT_FOUND', 404, 'Report type not found.');
        }

        $method = 'get' . str_replace('_', '', ucwords($type, '_')) . 'Report';
        if (!method_exists($this, $method)) {
            throw new AppException('Report not implemented', 'NOT_FOUND', 404, 'Report not implemented yet.');
        }

        return $this->$method($filters);
    }

    private function getVendorsReport(array $filters): array
    {
        $stmt = $this->db->execute(
            'SELECT v.id, v.name, v.domain, v.contact_email, v.verification_status, v.risk_rating,
                    v.effective_date, v.expiry_date, v.is_blocked, v.created_at
             FROM vendors v ORDER BY v.name'
        );
        return [
            'columns' => ['ID', 'Name', 'Domain', 'Email', 'Verification', 'Risk', 'Effective', 'Expiry', 'Blocked', 'Created'],
            'rows' => $stmt->fetchAll(),
            'summary' => [],
        ];
    }

    private function getRequisitionsReport(array $filters): array
    {
        $stmt = $this->db->execute(
            'SELECT pr.requisition_number, d.name as department, u.username as requestor,
                    pr.status, pr.priority, pr.total_amount, pr.currency, pr.created_at
             FROM purchase_requisitions pr
             INNER JOIN departments d ON d.id = pr.department_id
             INNER JOIN users u ON u.id = pr.requested_by
             ORDER BY pr.created_at DESC'
        );
        return [
            'columns' => ['Number', 'Department', 'Requestor', 'Status', 'Priority', 'Amount', 'Currency', 'Created'],
            'rows' => $stmt->fetchAll(),
            'summary' => [],
        ];
    }

    private function getPurchaseOrdersReport(array $filters): array
    {
        $stmt = $this->db->execute(
            'SELECT po.po_number, v.name as vendor, po.status, po.total_amount, po.currency, po.issued_at, po.created_at
             FROM purchase_orders po
             INNER JOIN vendors v ON v.id = po.vendor_id
             ORDER BY po.created_at DESC'
        );
        return [
            'columns' => ['PO Number', 'Vendor', 'Status', 'Amount', 'Currency', 'Issued', 'Created'],
            'rows' => $stmt->fetchAll(),
            'summary' => [],
        ];
    }

    private function getInvoicesReport(array $filters): array
    {
        $stmt = $this->db->execute(
            'SELECT i.id, i.invoice_number, v.name as vendor, i.total_amount, i.currency,
                    i.extraction_status, i.invoice_date, i.due_date, i.created_at
             FROM invoices i
             LEFT JOIN vendors v ON v.id = i.vendor_id
             ORDER BY i.created_at DESC'
        );
        return [
            'columns' => ['ID', 'Invoice Number', 'Vendor', 'Amount', 'Currency', 'Status', 'Date', 'Due', 'Created'],
            'rows' => $stmt->fetchAll(),
            'summary' => [],
        ];
    }

    private function getBudgetUtilizationReport(array $filters): array
    {
        $stmt = $this->db->execute(
            'SELECT d.name as department, d.code, b.fiscal_year, b.total_amount, b.allocated_amount, b.spent_amount,
                    b.currency,
                    ROUND((b.allocated_amount / NULLIF(b.total_amount, 0)) * 100, 2) as utilization_pct
             FROM budgets b
             INNER JOIN departments d ON d.id = b.department_id
             ORDER BY b.fiscal_year DESC, d.name'
        );
        return [
            'columns' => ['Department', 'Code', 'Year', 'Total', 'Allocated', 'Spent', 'Currency', 'Utilization %'],
            'rows' => $stmt->fetchAll(),
            'summary' => [],
        ];
    }

    private function getMatchingReport(array $filters): array
    {
        $stmt = $this->db->execute(
            'SELECT mr.id, po.po_number, i.invoice_number, mr.status, mr.po_amount, mr.grn_amount,
                    mr.invoice_amount, mr.tolerance_pct, mr.matched_at
             FROM match_results mr
             INNER JOIN purchase_orders po ON po.id = mr.po_id
             INNER JOIN invoices i ON i.id = mr.invoice_id
             ORDER BY mr.created_at DESC'
        );
        return [
            'columns' => ['ID', 'PO', 'Invoice', 'Status', 'PO Amount', 'GRN Amount', 'Invoice Amount', 'Tolerance', 'Matched At'],
            'rows' => $stmt->fetchAll(),
            'summary' => [],
        ];
    }

    private function getComplianceReport(array $filters): array
    {
        $stmt = $this->db->execute(
            'SELECT id, alert_type, severity, entity_type, entity_id, title, is_resolved, created_at
             FROM compliance_alerts ORDER BY created_at DESC'
        );
        return [
            'columns' => ['ID', 'Type', 'Severity', 'Entity Type', 'Entity ID', 'Title', 'Resolved', 'Created'],
            'rows' => $stmt->fetchAll(),
            'summary' => [],
        ];
    }

    private function getAuditTrailReport(array $filters): array
    {
        $stmt = $this->db->execute(
            'SELECT id, user_id, action, entity_type, entity_id, details, ip_address, created_at
             FROM audit_logs ORDER BY created_at DESC LIMIT 1000'
        );
        return [
            'columns' => ['ID', 'User ID', 'Action', 'Entity', 'Entity ID', 'Details', 'IP', 'Created'],
            'rows' => $stmt->fetchAll(),
            'summary' => [],
        ];
    }
}
