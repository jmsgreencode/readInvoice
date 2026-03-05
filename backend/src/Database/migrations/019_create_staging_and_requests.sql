-- Vendor staging for CSV/bulk import
CREATE TABLE IF NOT EXISTS vendor_staging (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id        VARCHAR(36) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    domain          VARCHAR(255) DEFAULT NULL,
    contact_email   VARCHAR(255) DEFAULT NULL,
    notes           TEXT DEFAULT NULL,
    raw_data        JSON DEFAULT NULL,
    validation_status ENUM('pending','valid','invalid') DEFAULT 'pending',
    validation_errors TEXT DEFAULT NULL,
    promoted_vendor_id BIGINT UNSIGNED DEFAULT NULL,
    imported_by     BIGINT UNSIGNED NOT NULL,
    reviewed_by     BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at     TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vstage_batch (batch_id),
    INDEX idx_vstage_status (validation_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendor requests (requestor intake)
CREATE TABLE IF NOT EXISTS vendor_requests (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_number  VARCHAR(50) NOT NULL UNIQUE,
    requested_by    BIGINT UNSIGNED NOT NULL,
    vendor_name     VARCHAR(255) NOT NULL,
    vendor_website  VARCHAR(500) DEFAULT NULL,
    vendor_contact_name VARCHAR(255) DEFAULT NULL,
    vendor_contact_email VARCHAR(255) DEFAULT NULL,
    vendor_contact_phone VARCHAR(50) DEFAULT NULL,
    business_justification TEXT NOT NULL,
    category        VARCHAR(100) DEFAULT NULL,
    status          ENUM('draft','submitted','under_review','approved','rejected') DEFAULT 'draft',
    reviewed_by     BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at     TIMESTAMP NULL DEFAULT NULL,
    review_notes    TEXT DEFAULT NULL,
    promoted_vendor_id BIGINT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    INDEX idx_vreq_status (status),
    INDEX idx_vreq_requestor (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supporting documents for vendor requests
CREATE TABLE IF NOT EXISTS vendor_request_documents (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id      BIGINT UNSIGNED NOT NULL,
    file_name       VARCHAR(512) NOT NULL,
    file_path       VARCHAR(1024) NOT NULL,
    file_size       INT UNSIGNED DEFAULT NULL,
    mime_type       VARCHAR(100) DEFAULT NULL,
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES vendor_requests(id) ON DELETE CASCADE,
    INDEX idx_vreqdoc_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File imports tracking
CREATE TABLE IF NOT EXISTS file_imports (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id        VARCHAR(36) NOT NULL UNIQUE,
    file_name       VARCHAR(512) NOT NULL,
    file_type       ENUM('csv','xlsx','xls') NOT NULL,
    import_type     VARCHAR(50) NOT NULL,
    status          ENUM('pending','processing','completed','failed','partial') DEFAULT 'pending',
    total_rows      INT UNSIGNED DEFAULT 0,
    processed_rows  INT UNSIGNED DEFAULT 0,
    error_rows      INT UNSIGNED DEFAULT 0,
    error_log       JSON DEFAULT NULL,
    imported_by     BIGINT UNSIGNED NOT NULL,
    started_at      TIMESTAMP NULL DEFAULT NULL,
    completed_at    TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imported_by) REFERENCES users(id),
    INDEX idx_fimport_status (status),
    INDEX idx_fimport_type (import_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
