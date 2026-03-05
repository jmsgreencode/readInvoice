-- User-Role many-to-many
CREATE TABLE IF NOT EXISTS user_roles (
    user_id   BIGINT UNSIGNED NOT NULL,
    role_id   BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED DEFAULT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Separation of Duties conflict rules
CREATE TABLE IF NOT EXISTS sod_conflict_rules (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_a_id   BIGINT UNSIGNED NOT NULL,
    role_b_id   BIGINT UNSIGNED NOT NULL,
    reason      VARCHAR(500) NOT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_a_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (role_b_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY uq_sod_pair (role_a_id, role_b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add fields to users table
ALTER TABLE users
    ADD COLUMN department_id BIGINT UNSIGNED DEFAULT NULL AFTER role,
    ADD COLUMN display_name VARCHAR(255) DEFAULT NULL AFTER department_id,
    ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER display_name;

-- Migrate existing admin user to super_admin role
INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u, roles r WHERE u.username = 'admin' AND r.name = 'super_admin';

-- Seed SoD conflict rules
INSERT INTO sod_conflict_rules (role_a_id, role_b_id, reason) VALUES
((SELECT id FROM roles WHERE name = 'procurement_admin'), (SELECT id FROM roles WHERE name = 'finance_officer'), 'Procurement and finance must be separate for fraud prevention'),
((SELECT id FROM roles WHERE name = 'procurement_officer'), (SELECT id FROM roles WHERE name = 'finance_officer'), 'Procurement and finance must be separate for fraud prevention');
