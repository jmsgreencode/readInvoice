CREATE TABLE IF NOT EXISTS circuit_breaker_state (
    service_name    VARCHAR(100) PRIMARY KEY,
    state           ENUM('closed','open','half_open') DEFAULT 'closed',
    failure_count   INT UNSIGNED DEFAULT 0,
    last_failure_at TIMESTAMP NULL DEFAULT NULL,
    opened_at       TIMESTAMP NULL DEFAULT NULL,
    half_open_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
