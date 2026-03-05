-- Grant privileges for application user
-- Principle of least privilege: only DML operations, no DDL in production
-- DDL is handled by migrations run with elevated credentials

GRANT SELECT, INSERT, UPDATE, DELETE ON readinvoice.* TO 'readinvoice'@'%';
FLUSH PRIVILEGES;
