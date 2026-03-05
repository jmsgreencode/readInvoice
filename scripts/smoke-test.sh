#!/bin/bash
set -euo pipefail

BACKEND_URL="${BACKEND_URL:-http://localhost:8080}"
FRONTEND_URL="${FRONTEND_URL:-http://localhost:8081}"
PASS=0
FAIL=0

check() {
    local NAME="$1"
    local URL="$2"
    local EXPECTED_STATUS="$3"

    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$URL" 2>/dev/null || echo "000")

    if [ "$HTTP_STATUS" == "$EXPECTED_STATUS" ]; then
        echo "PASS: $NAME -> $HTTP_STATUS"
        PASS=$((PASS + 1))
    else
        echo "FAIL: $NAME -> Expected $EXPECTED_STATUS, got $HTTP_STATUS"
        FAIL=$((FAIL + 1))
    fi
}

echo "=== Smoke Tests ==="
echo ""

# Backend health checks
check "Backend Health" "${BACKEND_URL}/api/health" "200"
check "Backend Ready" "${BACKEND_URL}/api/health/ready" "200"

# Auth required endpoints
check "Vendors (no auth)" "${BACKEND_URL}/api/vendors" "401"
check "Invoices (no auth)" "${BACKEND_URL}/api/invoices" "401"
check "Emails (no auth)" "${BACKEND_URL}/api/emails" "401"

# Frontend
check "Frontend Dashboard" "${FRONTEND_URL}/" "200"
check "Frontend Login" "${FRONTEND_URL}/login" "200"

# 404 handling
check "Backend 404" "${BACKEND_URL}/api/nonexistent" "404"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="

if [ $FAIL -gt 0 ]; then
    exit 1
fi
