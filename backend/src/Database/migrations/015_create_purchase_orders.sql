CREATE TABLE IF NOT EXISTS purchase_orders (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number       VARCHAR(50) NOT NULL UNIQUE,
    requisition_id  BIGINT UNSIGNED DEFAULT NULL,
    vendor_id       BIGINT UNSIGNED NOT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    status          ENUM('draft','issued','partially_received','fully_received','closed','cancelled') DEFAULT 'draft',
    total_amount    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency        CHAR(3) DEFAULT 'USD',
    issued_at       TIMESTAMP NULL DEFAULT NULL,
    notes           TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(id) ON DELETE SET NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_po_vendor (vendor_id),
    INDEX idx_po_status (status),
    INDEX idx_po_requisition (requisition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS po_line_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id           BIGINT UNSIGNED NOT NULL,
    description     VARCHAR(500) NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_price     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    received_qty    DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    INDEX idx_poli_po (po_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
