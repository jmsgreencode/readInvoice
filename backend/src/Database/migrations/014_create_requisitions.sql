CREATE TABLE IF NOT EXISTS purchase_requisitions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_number VARCHAR(50) NOT NULL UNIQUE,
    department_id   BIGINT UNSIGNED NOT NULL,
    requested_by    BIGINT UNSIGNED NOT NULL,
    vendor_id       BIGINT UNSIGNED DEFAULT NULL,
    status          ENUM('draft','submitted','approved','rejected','cancelled') DEFAULT 'draft',
    priority        ENUM('low','medium','high','urgent') DEFAULT 'medium',
    justification   TEXT DEFAULT NULL,
    total_amount    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency        CHAR(3) DEFAULT 'USD',
    budget_id       BIGINT UNSIGNED DEFAULT NULL,
    approved_by     BIGINT UNSIGNED DEFAULT NULL,
    approved_at     TIMESTAMP NULL DEFAULT NULL,
    rejected_reason VARCHAR(500) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE SET NULL,
    INDEX idx_req_status (status),
    INDEX idx_req_department (department_id),
    INDEX idx_req_requestor (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requisition_line_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id  BIGINT UNSIGNED NOT NULL,
    description     VARCHAR(500) NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_price     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    INDEX idx_reqli_requisition (requisition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
