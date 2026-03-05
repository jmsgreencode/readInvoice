CREATE TABLE IF NOT EXISTS email_notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_type VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_user_id BIGINT UNSIGNED DEFAULT NULL,
    subject         VARCHAR(500) NOT NULL,
    body_html       TEXT NOT NULL,
    body_text       TEXT DEFAULT NULL,
    entity_type     VARCHAR(50) DEFAULT NULL,
    entity_id       BIGINT UNSIGNED DEFAULT NULL,
    status          ENUM('queued','sending','sent','failed') DEFAULT 'queued',
    attempts        TINYINT UNSIGNED DEFAULT 0,
    last_error      TEXT DEFAULT NULL,
    sent_at         TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emailnotif_status (status),
    INDEX idx_emailnotif_type (notification_type),
    INDEX idx_emailnotif_recipient (recipient_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
