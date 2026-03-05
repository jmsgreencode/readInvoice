CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(100) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin','user') DEFAULT 'user',
    is_active       TINYINT(1) DEFAULT 1,
    last_login_at   TIMESTAMP DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin user (password: admin123) - CHANGE IN PRODUCTION
-- Hash generated with password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$10$PhrL8qE8gch4wW4SvAvDPuwoAwMhKdJ89.B30t.OeaESHFV2ewnSi', 'admin');
