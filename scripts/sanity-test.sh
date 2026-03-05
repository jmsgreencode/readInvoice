#!/bin/bash
set -euo pipefail

PASS=0
FAIL=0

sanity_check() {
    local NAME="$1"
    local CMD="$2"

    if eval "$CMD" > /dev/null 2>&1; then
        echo "PASS: $NAME"
        PASS=$((PASS + 1))
    else
        echo "FAIL: $NAME"
        FAIL=$((FAIL + 1))
    fi
}

echo "=== Sanity Tests ==="
echo ""

# Docker services running
sanity_check "Docker Compose services up" "docker compose ps --format json | grep -q 'running'"
sanity_check "Backend container healthy" "docker compose exec backend php -v"
sanity_check "Frontend container healthy" "docker compose exec frontend php -v"
sanity_check "MySQL reachable" "docker compose exec mysql-primary mysqladmin ping -h localhost --silent"

# Database schema
sanity_check "Vendors table exists" "docker compose exec backend php -r \"
    \\\$pdo = new PDO('mysql:host=mysql-router;port=6446;dbname=readinvoice', 'readinvoice', getenv('DB_PASS'));
    \\\$pdo->query('SELECT 1 FROM vendors LIMIT 1');
\""

# Tesseract
sanity_check "Tesseract installed" "docker compose exec backend tesseract --version"

# File system
sanity_check "Upload dir writable" "docker compose exec backend test -w /var/www/uploads"

# PHP extensions
sanity_check "PDO MySQL extension" "docker compose exec backend php -m | grep -q pdo_mysql"
sanity_check "GD extension" "docker compose exec backend php -m | grep -q gd"
sanity_check "Intl extension" "docker compose exec backend php -m | grep -q intl"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="

if [ $FAIL -gt 0 ]; then
    exit 1
fi
