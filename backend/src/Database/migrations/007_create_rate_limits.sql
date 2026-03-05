CREATE TABLE IF NOT EXISTS rate_limits (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_key        VARCHAR(255) NOT NULL,
    tokens          INT UNSIGNED NOT NULL DEFAULT 0,
    last_refill_at  TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6),
    expires_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_key (rate_key),
    INDEX idx_rate_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
