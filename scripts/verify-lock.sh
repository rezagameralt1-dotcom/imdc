#!/usr/bin/env bash
# IMDC: Final verification script - locks and hardens marketplace guardrail
# Checks: guardrail PASS, no-host-cron, no-application-string

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR" || exit 1

ERRORS=0

echo "=== IMDC Lock Verification ==="
echo

# Check 1: Guardrail PASS
echo "Check 1: Marketplace guardrail must PASS..."
if [[ -f "$SCRIPT_DIR/verify-marketplace.sh" ]]; then
    if docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && IMDC_API_BASE_URL=http://web:80 ./scripts/verify-marketplace.sh --in-container" > /tmp/guardrail_output.txt 2>&1; then
        if grep -q "Marketplace Guardrail PASSED" /tmp/guardrail_output.txt; then
            echo "✓ Guardrail PASSED"
        else
            echo "✗ Guardrail did not report PASS"
            ERRORS=$((ERRORS + 1))
        fi
    else
        echo "✗ Guardrail execution failed"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo "✗ verify-marketplace.sh not found"
    ERRORS=$((ERRORS + 1))
fi
echo

# Check 2: No host cron (IMDC schedule:run should be commented or absent)
echo "Check 2: Host cron should be disabled/commented..."
ACTIVE_CRON=$(crontab -l 2>/dev/null | grep -c "^[^#].*schedule:run.*IMDC.*backend" 2>/dev/null || echo "0")
ACTIVE_CRON=${ACTIVE_CRON//[^0-9]/}  # Remove any non-numeric characters
if [[ "${ACTIVE_CRON:-0}" -eq 0 ]]; then
    echo "✓ No active host cron found"
else
    echo "✗ Active host cron found (should be commented or removed)"
    crontab -l 2>/dev/null | grep "^[^#].*schedule:run.*IMDC" || true
    ERRORS=$((ERRORS + 1))
fi
echo

# Check 3: No "application" hostname in code (hostname-only; ignore MIME/docs)
echo "Check 3: No 'application' hostname found in code..."

# Only flag if "application" is used as a host in a URL / host:port form.
# Exclusions: docs/, README*, *.md, docs/openapi/*.yaml, vendor/, composer.lock, backup_*/
# NOTE: We intentionally DO NOT match "application/json" or other MIME types.
# Scope: Only scan scripts/verify-marketplace.sh, app/, routes/, infra/ (not verify-lock.sh or backups)

HOSTNAME_HITS="$(
  grep -RIn \
    --exclude-dir=vendor \
    --exclude-dir=node_modules \
    --exclude-dir=storage \
    --exclude-dir=docs \
    --exclude-dir=backup_* \
    --exclude=verify-lock.sh \
    --exclude=verify-lock.sh.bak* \
    --exclude=*.bak* \
    -E '(https?://application(?::[0-9]+)?|(^|[^A-Za-z0-9_])application:(80|8080)([^0-9]|$)|//application(?::[0-9]+)?([^A-Za-z0-9_]|$))' \
    scripts/verify-marketplace.sh app routes infra 2>/dev/null || true
)"


if [ -z "$HOSTNAME_HITS" ]; then
  echo "✓ No 'application' hostname usage detected"
else
  echo "✗ Found invalid 'application' hostname usage:"
  echo "$HOSTNAME_HITS"
  ERRORS=$((ERRORS + 1))
fi
echo

# Summary
if [[ $ERRORS -eq 0 ]]; then
    echo "=== All checks PASSED ==="
    exit 0
else
    echo "=== FAILED: $ERRORS check(s) failed ==="
    exit 1
fi
