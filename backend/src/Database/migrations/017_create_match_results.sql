CREATE TABLE IF NOT EXISTS match_results (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id           BIGINT UNSIGNED NOT NULL,
    grn_id          BIGINT UNSIGNED DEFAULT NULL,
    invoice_id      BIGINT UNSIGNED NOT NULL,
    status          ENUM('matched','mismatched','partial','pending') DEFAULT 'pending',
    po_amount       DECIMAL(15,2) NOT NULL,
    grn_amount      DECIMAL(15,2) DEFAULT NULL,
    invoice_amount  DECIMAL(15,2) NOT NULL,
    tolerance_pct   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    discrepancies   JSON DEFAULT NULL,
    matched_by      BIGINT UNSIGNED DEFAULT NULL,
    matched_at      TIMESTAMP NULL DEFAULT NULL,
    notes           TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (grn_id) REFERENCES goods_received_notes(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    INDEX idx_match_po (po_id),
    INDEX idx_match_invoice (invoice_id),
    INDEX idx_match_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add po_id to invoices for linking
ALTER TABLE invoices
    ADD COLUMN po_id BIGINT UNSIGNED DEFAULT NULL AFTER vendor_id,
    ADD INDEX idx_invoices_po (po_id),
    ADD CONSTRAINT fk_invoices_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL;
