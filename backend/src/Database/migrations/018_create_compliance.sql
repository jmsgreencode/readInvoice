CREATE TABLE IF NOT EXISTS compliance_settings (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(100) NOT NULL UNIQUE,
    setting_value   VARCHAR(500) NOT NULL,
    description     TEXT DEFAULT NULL,
    updated_by      BIGINT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compliance_alerts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_type      ENUM('vendor_expiry','document_expiry','budget_threshold','match_discrepancy','vendor_blocked') NOT NULL,
    severity        ENUM('info','warning','critical') DEFAULT 'warning',
    entity_type     VARCHAR(50) NOT NULL,
    entity_id       BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    is_resolved     TINYINT(1) DEFAULT 0,
    resolved_by     BIGINT UNSIGNED DEFAULT NULL,
    resolved_at     TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_calert_type (alert_type),
    INDEX idx_calert_entity (entity_type, entity_id),
    INDEX idx_calert_resolved (is_resolved),
    INDEX idx_calert_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default compliance settings
INSERT INTO compliance_settings (setting_key, setting_value, description) VALUES
('vendor_expiry_warning_days', '30', 'Days before vendor expiry to trigger warning alert'),
('document_expiry_warning_days', '30', 'Days before document expiry to trigger warning alert'),
('budget_warning_threshold_pct', '80', 'Budget utilization percentage to trigger warning'),
('budget_critical_threshold_pct', '95', 'Budget utilization percentage to trigger critical alert'),
('match_tolerance_pct', '2.00', 'Percentage tolerance for 3-way match discrepancies'),
('auto_block_expired_vendors', 'true', 'Automatically block vendors past their expiry date');
