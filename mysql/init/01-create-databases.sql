-- Initialize database schema
-- This runs on first container startup only

CREATE DATABASE IF NOT EXISTS readinvoice
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE readinvoice;

-- Migrations table (managed by MigrationRunner)
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
