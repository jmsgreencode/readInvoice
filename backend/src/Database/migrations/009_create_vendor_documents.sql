CREATE TABLE IF NOT EXISTS vendor_documents (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id       BIGINT UNSIGNED NOT NULL,
    document_type   VARCHAR(100) NOT NULL,
    file_name       VARCHAR(512) NOT NULL,
    file_path       VARCHAR(1024) NOT NULL,
    file_size       INT UNSIGNED DEFAULT NULL,
    mime_type       VARCHAR(100) DEFAULT NULL,
    uploaded_by     BIGINT UNSIGNED NOT NULL,
    expiry_date     DATE DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vdocs_vendor (vendor_id),
    INDEX idx_vdocs_type (document_type),
    INDEX idx_vdocs_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
