#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

if [ ! -f SHA256SUMS ]; then
    echo "ERROR: SHA256SUMS file not found. Run generate-checksums.sh first."
    exit 1
fi

echo "Verifying SHA-256 checksums..."

FAILED=0
while IFS= read -r line; do
    HASH=$(echo "$line" | awk '{print $1}')
    FILE=$(echo "$line" | awk '{print $2}')

    if [ ! -f "$FILE" ]; then
        echo "MISSING: $FILE"
        FAILED=$((FAILED + 1))
        continue
    fi

    ACTUAL=$(shasum -a 256 "$FILE" | awk '{print $1}')
    if [ "$HASH" != "$ACTUAL" ]; then
        echo "MISMATCH: $FILE"
        echo "  Expected: $HASH"
        echo "  Actual:   $ACTUAL"
        FAILED=$((FAILED + 1))
    fi
done < SHA256SUMS

if [ $FAILED -gt 0 ]; then
    echo ""
    echo "FAILED: $FAILED file(s) failed verification."
    exit 1
else
    TOTAL=$(wc -l < SHA256SUMS | tr -d ' ')
    echo "OK: All ${TOTAL} files verified."
fi
