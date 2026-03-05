CREATE TABLE IF NOT EXISTS goods_received_notes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_number      VARCHAR(50) NOT NULL UNIQUE,
    po_id           BIGINT UNSIGNED NOT NULL,
    received_by     BIGINT UNSIGNED NOT NULL,
    status          ENUM('draft','confirmed','cancelled') DEFAULT 'draft',
    received_date   DATE NOT NULL,
    notes           TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_grn_po (po_id),
    INDEX idx_grn_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grn_line_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_id          BIGINT UNSIGNED NOT NULL,
    po_line_item_id BIGINT UNSIGNED NOT NULL,
    received_qty    DECIMAL(10,2) NOT NULL DEFAULT 0,
    accepted_qty    DECIMAL(10,2) NOT NULL DEFAULT 0,
    rejected_qty    DECIMAL(10,2) NOT NULL DEFAULT 0,
    rejection_reason VARCHAR(500) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grn_id) REFERENCES goods_received_notes(id) ON DELETE CASCADE,
    FOREIGN KEY (po_line_item_id) REFERENCES po_line_items(id),
    INDEX idx_grnli_grn (grn_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
