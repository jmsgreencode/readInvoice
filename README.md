# proCom

Enterprise invoice processing system that integrates with Microsoft Outlook to automatically detect, extract, and manage invoices from vendor emails.

## Architecture

```
                    +-------------------+
                    |  Outlook Add-in   |
                    |  (TypeScript)     |
                    +--------+----------+
                             |
                             | HTTPS POST /api/emails/ingest
                             v
+------------------+    +----+--------+    +-------------------+
|   Frontend       |    |  Backend    |    |  Email Worker     |
|   React SPA     +--->|  PHP/Slim   |<---+  (Graph API Poll) |
|   Port 9001      |    |  Port 8080  |    +-------------------+
+------------------+    +------+------+
                               |
                        +------+------+
                        | MySQL Router|
                        | 6446/6447   |
                        +------+------+
                               |
                    +----------+----------+
                    | MySQL 8.4 LTS       |
                    | (InnoDB Cluster)    |
                    +---------------------+
```

## Components

| Component | Description | Technology |
|-----------|-------------|------------|
| **Backend** | REST API microservice | PHP 8.2 / Slim 4 / PHP-FPM |
| **Frontend** | Dashboard with vendor-grouped email/invoice views | React 18 SPA / nginx |
| **Email Worker** | Background daemon polling Microsoft Graph API | PHP 8.2 CLI |
| **Outlook Add-in** | In-Outlook invoice detection and forwarding | TypeScript / Office.js |
| **MySQL** | Primary data store with InnoDB Cluster | MySQL 8.4 LTS |
| **MySQL Router** | Automatic read/write routing and failover | MySQL Router 8.4 |

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Git

### Setup

```bash
# Clone and configure
cp .env.example .env
# Edit .env with your credentials

# Build and start
make setup

# Or manually:
docker compose build
docker compose up -d
sleep 10
make migrate
```

### Access

- **Frontend**: http://localhost
- **Backend API**: http://localhost:8080
- **API Health**: http://localhost:8080/api/health

## Configuration

All configuration is via environment variables. See `.env.example` for the complete list.

**Required variables** (change from defaults before deployment):

| Variable | Description |
|----------|-------------|
| `MYSQL_ROOT_PASSWORD` | MySQL root password |
| `MYSQL_PASSWORD` | Application database password |
| `JWT_SECRET` | JWT signing secret (min 32 chars) |
| `GRAPH_API_TOKEN` | Microsoft Graph API access token |
| `VSPHERE_URL` | vSphere vCenter URL |
| `VSPHERE_USER` | vSphere credentials |
| `VSPHERE_PASS` | vSphere credentials |

## API Reference

See [MANIFEST.md](MANIFEST.md) for the complete API endpoint reference.

### Authentication

```bash
# Login
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}'

# Use token
curl http://localhost:8080/api/vendors \
  -H "Authorization: Bearer <token>"
```

### Response Format

All API responses follow a consistent envelope:

```json
{
  "success": true,
  "data": { },
  "meta": {
    "request_id": "uuid",
    "timestamp": "ISO-8601"
  }
}
```

Error responses include role-appropriate detail:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "User-safe message",
    "detail": "Technical detail (admin only)"
  }
}
```

## Testing

```bash
# Unit tests
make test

# Unit tests with coverage report
make test-coverage

# Smoke tests (requires running services)
make smoke

# Sanity tests (requires running services)
make sanity

# All tests
make test-all
```

## Deployment

### Docker Compose (Development)

```bash
make setup
```

### Kubernetes (TKGS)

Kubernetes manifests are in `k8s/`. Compatible with Tanzu Kubernetes Grid Service (TKGS).

```bash
# Apply manifests
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/configmaps/
kubectl apply -f k8s/secrets/
kubectl apply -f k8s/services/
kubectl apply -f k8s/ingress.yaml
kubectl apply -f k8s/networkpolicies.yaml
kubectl apply -f k8s/rbac.yaml
```

### Integrity Verification

```bash
# Generate checksums
make checksums

# Verify checksums
make verify-checksums
```

## Security

This project follows OWASP coding standards. Key security controls:

- **Authentication**: JWT with short-lived access tokens and refresh tokens
- **Authorization**: Role-based access control (admin/user)
- **SQL Injection**: Parameterized queries only (PDO prepared statements)
- **XSS**: Output encoding and Content Security Policy headers
- **Rate Limiting**: Token bucket algorithm per IP/user
- **Circuit Breakers**: Protect against cascading failures from external services
- **File Integrity**: SHA-256 checksums on all stored PDFs
- **Logging**: Structured JSON (Splunk-compatible) with sensitive data redaction
- **Error Handling**: Users see safe messages; admins see full technical details

See [MANIFEST.md](MANIFEST.md) for the complete security controls matrix.

## Logging

All services output structured JSON logs to stdout, compatible with Splunk and other log aggregation platforms.

Log fields include: `timestamp`, `level`, `message`, `service`, `request_id`, `user_id`, `context`, `duration_ms`, `http` (method, path, status, client_ip).

Sensitive fields (`password`, `token`, `authorization`, `secret`, `api_key`) are automatically redacted.

## Project Files

See [MANIFEST.md](MANIFEST.md) for the complete project manifest and [GENERATION_LOG.md](GENERATION_LOG.md) for the generation audit trail.
