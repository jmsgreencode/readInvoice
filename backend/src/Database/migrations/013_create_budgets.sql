CREATE TABLE IF NOT EXISTS budgets (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id   BIGINT UNSIGNED NOT NULL,
    fiscal_year     SMALLINT UNSIGNED NOT NULL,
    total_amount    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    spent_amount    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency        CHAR(3) DEFAULT 'USD',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY uq_budget_dept_year (department_id, fiscal_year),
    INDEX idx_budgets_year (fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
