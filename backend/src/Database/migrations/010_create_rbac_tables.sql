-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    is_system   TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    module      VARCHAR(50) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role-Permission many-to-many
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default roles
INSERT INTO roles (name, display_name, description, is_system) VALUES
('super_admin', 'Super Administrator', 'Full system access', 1),
('procurement_admin', 'Procurement Administrator', 'Manages vendors, POs, and procurement workflows', 1),
('procurement_officer', 'Procurement Officer', 'Processes requisitions and creates POs', 1),
('finance_officer', 'Finance Officer', 'Manages invoices, budgets, and financial reporting', 1),
('department_manager', 'Department Manager', 'Manages department budgets and approves requisitions', 1),
('requestor', 'Requestor', 'Submits vendor requests and requisitions', 1);

-- Seed permissions
INSERT INTO permissions (name, display_name, module) VALUES
-- Vendor module
('vendors.view', 'View Vendors', 'vendors'),
('vendors.create', 'Create Vendors', 'vendors'),
('vendors.edit', 'Edit Vendors', 'vendors'),
('vendors.delete', 'Delete Vendors', 'vendors'),
('vendors.verify', 'Verify Vendors', 'vendors'),
('vendors.block', 'Block/Unblock Vendors', 'vendors'),
('vendors.documents', 'Manage Vendor Documents', 'vendors'),
-- Requisition module
('requisitions.view', 'View Requisitions', 'requisitions'),
('requisitions.create', 'Create Requisitions', 'requisitions'),
('requisitions.approve', 'Approve Requisitions', 'requisitions'),
('requisitions.reject', 'Reject Requisitions', 'requisitions'),
-- Purchase Order module
('purchase_orders.view', 'View Purchase Orders', 'purchase_orders'),
('purchase_orders.create', 'Create Purchase Orders', 'purchase_orders'),
('purchase_orders.edit', 'Edit Purchase Orders', 'purchase_orders'),
-- GRN module
('grns.view', 'View Goods Received Notes', 'grns'),
('grns.create', 'Create Goods Received Notes', 'grns'),
-- Invoice module
('invoices.view', 'View Invoices', 'invoices'),
('invoices.create', 'Upload Invoices', 'invoices'),
('invoices.edit', 'Edit Invoices', 'invoices'),
-- Matching module
('matching.view', 'View Match Results', 'matching'),
('matching.run', 'Run 3-Way Match', 'matching'),
-- Budget module
('budgets.view', 'View Budgets', 'budgets'),
('budgets.manage', 'Manage Budgets', 'budgets'),
-- Department module
('departments.view', 'View Departments', 'departments'),
('departments.manage', 'Manage Departments', 'departments'),
-- Compliance module
('compliance.view', 'View Compliance Alerts', 'compliance'),
('compliance.manage', 'Manage Compliance Settings', 'compliance'),
-- Reporting module
('reports.view', 'View Reports', 'reports'),
('reports.export', 'Export Reports', 'reports'),
-- Import module
('imports.upload', 'Upload Import Files', 'imports'),
('imports.view', 'View Import Jobs', 'imports'),
-- Staging module
('staging.view', 'View Vendor Staging', 'staging'),
('staging.promote', 'Promote Staged Vendors', 'staging'),
-- Vendor Requests module
('vendor_requests.create', 'Create Vendor Requests', 'vendor_requests'),
('vendor_requests.view_own', 'View Own Vendor Requests', 'vendor_requests'),
('vendor_requests.view_all', 'View All Vendor Requests', 'vendor_requests'),
('vendor_requests.review', 'Review Vendor Requests', 'vendor_requests'),
-- Admin module
('admin.users', 'Manage Users', 'admin'),
('admin.roles', 'Manage Roles', 'admin'),
('admin.logs', 'View Audit Logs', 'admin');

-- Assign permissions to roles
-- super_admin gets all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'super_admin';

-- procurement_admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'procurement_admin' AND p.name IN (
    'vendors.view', 'vendors.create', 'vendors.edit', 'vendors.verify', 'vendors.block', 'vendors.documents',
    'requisitions.view', 'requisitions.approve', 'requisitions.reject',
    'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit',
    'grns.view', 'grns.create',
    'invoices.view', 'invoices.create', 'invoices.edit',
    'matching.view', 'matching.run',
    'compliance.view', 'compliance.manage',
    'reports.view', 'reports.export',
    'imports.upload', 'imports.view',
    'staging.view', 'staging.promote',
    'vendor_requests.view_all', 'vendor_requests.review'
);

-- procurement_officer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'procurement_officer' AND p.name IN (
    'vendors.view', 'vendors.create', 'vendors.edit', 'vendors.documents',
    'requisitions.view',
    'purchase_orders.view', 'purchase_orders.create',
    'grns.view', 'grns.create',
    'invoices.view',
    'matching.view',
    'reports.view',
    'vendor_requests.view_all', 'vendor_requests.review'
);

-- finance_officer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'finance_officer' AND p.name IN (
    'vendors.view',
    'invoices.view', 'invoices.create', 'invoices.edit',
    'matching.view', 'matching.run',
    'budgets.view', 'budgets.manage',
    'reports.view', 'reports.export',
    'imports.upload', 'imports.view'
);

-- department_manager
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'department_manager' AND p.name IN (
    'vendors.view',
    'requisitions.view', 'requisitions.create', 'requisitions.approve', 'requisitions.reject',
    'purchase_orders.view',
    'budgets.view',
    'departments.view',
    'reports.view'
);

-- requestor
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'requestor' AND p.name IN (
    'vendors.view',
    'requisitions.view', 'requisitions.create',
    'vendor_requests.create', 'vendor_requests.view_own'
);
