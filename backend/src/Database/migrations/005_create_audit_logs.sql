CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED DEFAULT NULL,
    action          VARCHAR(100) NOT NULL,
    entity_type     VARCHAR(100) DEFAULT NULL,
    entity_id       BIGINT UNSIGNED DEFAULT NULL,
    detail_json     JSON DEFAULT NULL,
    ip_address      VARCHAR(45) DEFAULT NULL,
    request_id      CHAR(36) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_request (request_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
