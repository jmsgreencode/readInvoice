-- Add vendor lifecycle and verification fields
ALTER TABLE vendors
    ADD COLUMN effective_date DATE DEFAULT NULL AFTER notes,
    ADD COLUMN expiry_date DATE DEFAULT NULL AFTER effective_date,
    ADD COLUMN verification_status ENUM('unverified','pending','verified','rejected','suspended') DEFAULT 'unverified' AFTER expiry_date,
    ADD COLUMN verified_by BIGINT UNSIGNED DEFAULT NULL AFTER verification_status,
    ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL AFTER verified_by,
    ADD COLUMN risk_rating ENUM('low','medium','high','critical') DEFAULT NULL AFTER verified_at,
    ADD COLUMN is_blocked TINYINT(1) DEFAULT 0 AFTER risk_rating,
    ADD COLUMN blocked_reason VARCHAR(500) DEFAULT NULL AFTER is_blocked,
    ADD INDEX idx_vendors_expiry (expiry_date),
    ADD INDEX idx_vendors_verification (verification_status),
    ADD INDEX idx_vendors_blocked (is_blocked);
