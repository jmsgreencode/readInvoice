# Generation Log

## 2026-03-05T00:00:00Z - Initial Generation

- **Generator**: Claude Code (claude-opus-4-6)
- **Git Branch**: main
- **Components Generated**:
  - Backend PHP microservice (Slim 4 + PHP-FPM 8.2)
  - Frontend React SPA (replaced original Datastar SSE)
  - Outlook Add-in (TypeScript + Office.js)
  - MySQL 8.4 LTS database schema (7 migrations)
  - MySQL Router configuration
  - Docker Compose orchestration
  - TKGS-compatible Kubernetes manifests
  - Unit, smoke, and sanity test suites
  - SHA-256 checksum tooling
  - CI/CD scripts
- **Architecture Decisions**:
  - Slim Framework chosen for lightweight PSR-15 middleware stack
  - React 18 SPA with react-router-dom v6 (replaced Datastar SSE)
  - MySQL Router for transparent connection failover
  - Token bucket rate limiting backed by MySQL (cluster-safe)
  - Circuit breaker state persisted to MySQL (survives PHP-FPM restarts)
  - Monolog with JSON formatter for Splunk-compatible structured logging
  - Role-based error exposure: admin sees technical detail, user sees safe messages
- **Security Measures**:
  - All SQL via parameterized queries (PDO emulate_prepares=false)
  - SHA-256 checksum on every stored PDF (generate + verify after write)
  - OWASP Top 10 mitigations implemented
  - Sensitive data redaction in log output
  - Non-root container execution
- **Verification**: Run `make checksums` then `make verify-checksums`
