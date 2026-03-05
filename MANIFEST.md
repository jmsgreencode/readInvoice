# ReadInvoice - Project Manifest

## Project Information

| Field | Value |
|-------|-------|
| Project Name | ReadInvoice |
| Version | 1.0.0 |
| Generated | 2026-03-05 |
| PHP Version | 8.2-fpm-alpine |
| MySQL Version | 8.4 LTS |
| Architecture | Microservices (PHP/Slim) |
| Frontend | PHP + Datastar (SSE) |
| Container Runtime | Docker / Kubernetes (TKGS) |

## Service Inventory

| Service | Type | Port | Image Base |
|---------|------|------|------------|
| backend | REST API | 8080 | php:8.2-fpm-alpine |
| frontend | Web UI | 8081 | php:8.2-fpm-alpine |
| email-worker | Background Daemon | N/A | php:8.2-fpm-alpine |
| mysql-primary | Database | 3306 | mysql:8.4 |
| mysql-router | Connection Router | 6446/6447 | mysql/mysql-router:8.4 |
| outlook-addin | Outlook Plugin | N/A | node:20-alpine (build) |

## Database Schema

| Table | Purpose |
|-------|---------|
| vendors | Vendor/sender organization records |
| emails | Ingested email records from Outlook |
| invoices | Extracted invoice data with PDF references |
| users | Application user accounts |
| audit_logs | Security and operational audit trail |
| circuit_breaker_state | Circuit breaker persistence |
| rate_limits | Rate limiting token tracking |
| migrations | Schema version tracking |

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | /api/health | No | Health check |
| GET | /api/health/ready | No | Readiness probe |
| POST | /api/auth/login | No | User authentication |
| POST | /api/auth/refresh | No | Token refresh |
| GET | /api/vendors | Yes | List vendors |
| GET | /api/vendors/{id} | Yes | Get vendor |
| GET | /api/vendors/{id}/emails | Yes | Vendor emails |
| GET | /api/vendors/{id}/invoices | Yes | Vendor invoices |
| POST | /api/emails/ingest | Yes | Ingest email from Outlook |
| GET | /api/emails | Yes | List emails |
| GET | /api/emails/{id} | Yes | Get email |
| GET | /api/invoices | Yes | List invoices |
| GET | /api/invoices/{id} | Yes | Get invoice |
| PATCH | /api/invoices/{id} | Yes | Update invoice |
| GET | /api/invoices/{id}/pdf | Yes | Download PDF |
| GET | /api/vsphere/vms | Admin | List VMs |
| GET | /api/vsphere/datastores | Admin | List datastores |
| POST | /api/vsphere/vms/{id}/power | Admin | VM power action |

## Security Controls

| Control | Implementation |
|---------|---------------|
| Authentication | JWT (HS256) with refresh tokens |
| Authorization | Role-based (admin/user) |
| SQL Injection | Parameterized queries only (PDO emulate_prepares=false) |
| XSS | Output encoding (htmlspecialchars), CSP headers |
| CSRF | Token-based validation |
| Rate Limiting | Token bucket (MySQL-backed) |
| Circuit Breaker | Three-state (MySQL-persisted) |
| File Validation | MIME type + magic bytes verification |
| Checksum | SHA-256 for all stored PDFs |
| Input Validation | respect/validation + custom validators |
| Secrets | Environment variables only, never in code |
| Logging | Sensitive data redaction in all logs |
| Headers | X-Content-Type-Options, X-Frame-Options, CSP, HSTS |

## Compliance

- OWASP Top 10 (2021) addressed
- SHA-256 checksum verification for all artifacts
- Structured JSON logging (Splunk-compatible)
- Audit trail for all data operations
- Principle of least privilege (DB grants, RBAC)
- Non-root container execution
- Network segmentation (Kubernetes NetworkPolicy)
