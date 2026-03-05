#!/bin/bash
set -euo pipefail

# Generate SHA-256 checksums for all project deliverable files
# Excludes: .git, vendor, node_modules, coverage, and the checksum file itself

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "Generating SHA-256 checksums..."

cd "$PROJECT_ROOT"

find . -type f \
    -not -path './.git/*' \
    -not -path '*/vendor/*' \
    -not -path '*/node_modules/*' \
    -not -path '*/coverage/*' \
    -not -path '*/.phpunit.cache/*' \
    -not -path '*/mysql_data/*' \
    -not -path '*/uploads/*' \
    -not -name 'SHA256SUMS' \
    -not -name '.DS_Store' \
    | sort \
    | xargs shasum -a 256 > SHA256SUMS

TOTAL=$(wc -l < SHA256SUMS | tr -d ' ')
echo "Generated checksums for ${TOTAL} files -> SHA256SUMS"
