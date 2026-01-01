#!/usr/bin/env bash
# IMDC: Final verification script - locks and hardens marketplace guardrail
# Checks: guardrail PASS, no-host-cron, no-application-string

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR" || exit 1

# Helper: Detect if running inside container
# Inside container: /var/www/html exists AND docker CLI is not available
is_container() {
    [[ "${1:-}" == "--in-container" ]] && return 0
    [[ -d "/var/www/html" ]] && ! command -v docker >/dev/null 2>&1 && return 0
    [[ -f "/.dockerenv" ]] && return 0
    [[ -f "/proc/self/cgroup" ]] && grep -qE "docker|kubepods" /proc/self/cgroup 2>/dev/null && return 0
    return 1
}

# Helper: Check if docker compose is available
has_docker_compose() {
    command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1
}

ERRORS=0

echo "=== IMDC Lock Verification ==="
echo

# Check 1: Guardrail PASS
echo "Check 1: Marketplace guardrail must PASS..."
if [[ -f "$SCRIPT_DIR/verify-marketplace.sh" ]]; then
    GUARDRAIL_EXIT=0
    if is_container "${1:-}"; then
        # Running inside container: execute directly
        cd /var/www/html || exit 1
        IMDC_API_BASE_URL=http://web:80 ./scripts/verify-marketplace.sh --in-container > /tmp/guardrail_output.txt 2>&1 || GUARDRAIL_EXIT=$?
    elif has_docker_compose; then
        # Running on host: use docker compose
        docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && IMDC_API_BASE_URL=http://web:80 /var/www/html/scripts/verify-marketplace.sh --in-container" > /tmp/guardrail_output.txt 2>&1 || GUARDRAIL_EXIT=$?
    else
        # No docker compose: try direct execution
        "$SCRIPT_DIR/verify-marketplace.sh" > /tmp/guardrail_output.txt 2>&1 || GUARDRAIL_EXIT=$?
    fi
    
    if [[ $GUARDRAIL_EXIT -eq 0 ]]; then
        if grep -q "Marketplace Guardrail PASSED" /tmp/guardrail_output.txt; then
            echo "✓ Guardrail PASSED"
        else
            echo "✗ Guardrail did not report PASS"
            ERRORS=$((ERRORS + 1))
        fi
    else
        echo "✗ Guardrail execution failed (exit code: $GUARDRAIL_EXIT)"
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

# Check 3: NFT guardrail (if FEATURE_NFT enabled)
echo "Check 3: NFT guardrail (if FEATURE_NFT enabled)..."
# Read FEATURE_NFT from .env file (deterministic detection)
ENV_FILE="${ROOT_DIR}/.env"
FEATURE_NFT_ENABLED=false
if [[ -f "$ENV_FILE" ]]; then
    # Read FEATURE_NFT from .env file (before any modifications)
    FEATURE_NFT_FROM_ENV="$(grep -E "^FEATURE_NFT=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    # Normalize: check if it's explicitly set to true
    if [[ -n "$FEATURE_NFT_FROM_ENV" ]]; then
        FEATURE_NFT_NORMALIZED="$(echo "$FEATURE_NFT_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_NFT_NORMALIZED" == "true" ]] || [[ "$FEATURE_NFT_NORMALIZED" == "1" ]] || [[ "$FEATURE_NFT_NORMALIZED" == "yes" ]] || [[ "$FEATURE_NFT_NORMALIZED" == "on" ]]; then
            FEATURE_NFT_ENABLED=true
        fi
    fi
else
    # If .env doesn't exist, fall back to shell env (default: false)
    if [[ "${FEATURE_NFT:-false}" == "true" ]]; then
        FEATURE_NFT_ENABLED=true
    fi
fi

if [[ "$FEATURE_NFT_ENABLED" == "true" ]]; then
    if [[ -f "$SCRIPT_DIR/verify-nft.sh" ]]; then
        NFT_EXIT=0
        if is_container "${1:-}"; then
            # Running inside container: execute directly
            cd /var/www/html || exit 1
            IMDC_API_BASE_URL=http://web:80 ./scripts/verify-nft.sh --in-container > /tmp/nft_guardrail_output.txt 2>&1 || NFT_EXIT=$?
        elif has_docker_compose; then
            # Running on host: use docker compose
            docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && IMDC_API_BASE_URL=http://web:80 /var/www/html/scripts/verify-nft.sh --in-container" > /tmp/nft_guardrail_output.txt 2>&1 || NFT_EXIT=$?
        else
            # No docker compose: try direct execution
            "$SCRIPT_DIR/verify-nft.sh" > /tmp/nft_guardrail_output.txt 2>&1 || NFT_EXIT=$?
        fi
        
        if [[ $NFT_EXIT -eq 0 ]]; then
            if grep -q "NFT Guardrail PASSED" /tmp/nft_guardrail_output.txt; then
                echo "✓ NFT guardrail PASSED"
            else
                echo "✗ NFT guardrail did not report PASS"
                ERRORS=$((ERRORS + 1))
            fi
        else
            echo "✗ NFT guardrail execution failed (exit code: $NFT_EXIT)"
            ERRORS=$((ERRORS + 1))
        fi
    else
        echo "✗ verify-nft.sh not found"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo "  SKIPPED: FEATURE_NFT is not enabled (FEATURE_NFT=${FEATURE_NFT_FROM_ENV:-${FEATURE_NFT:-false}})"
fi
echo

# Check 3.5: Linking guardrail (if FEATURE_LINKING enabled)
echo "Check 3.5: Linking guardrail (if FEATURE_LINKING enabled)..."
FEATURE_LINKING_ENABLED=false
FEATURE_LINKING_VALUE=""

# Read from .env file first (deterministic)
if [[ -f "$ENV_FILE" ]]; then
    FEATURE_LINKING_FROM_ENV="$(grep -E "^FEATURE_LINKING=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    if [[ -n "$FEATURE_LINKING_FROM_ENV" ]]; then
        FEATURE_LINKING_VALUE="$FEATURE_LINKING_FROM_ENV"
        FEATURE_LINKING_NORMALIZED="$(echo "$FEATURE_LINKING_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_LINKING_NORMALIZED" == "true" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "1" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "yes" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "on" ]]; then
            FEATURE_LINKING_ENABLED=true
        fi
    fi
fi

# Fallback to shell env if not in .env
if [[ -z "$FEATURE_LINKING_VALUE" ]]; then
    FEATURE_LINKING_VALUE="${FEATURE_LINKING:-false}"
    if [[ "$FEATURE_LINKING_VALUE" == "true" ]] || [[ "$FEATURE_LINKING_VALUE" == "1" ]]; then
        FEATURE_LINKING_ENABLED=true
    fi
fi

if [[ "$FEATURE_LINKING_ENABLED" == "true" ]]; then
    if [[ -f "$SCRIPT_DIR/verify-linking.sh" ]]; then
        LINKING_EXIT=0
        if is_container "${1:-}"; then
            cd /var/www/html || exit 1
            FEATURE_LINKING=true IMDC_API_BASE_URL=http://web:80 ./scripts/verify-linking.sh --in-container > /tmp/linking_guardrail_output.txt 2>&1 || LINKING_EXIT=$?
        elif has_docker_compose; then
            docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && FEATURE_LINKING=true IMDC_API_BASE_URL=http://web:80 /var/www/html/scripts/verify-linking.sh --in-container" > /tmp/linking_guardrail_output.txt 2>&1 || LINKING_EXIT=$?
        else
            FEATURE_LINKING=true "$SCRIPT_DIR/verify-linking.sh" > /tmp/linking_guardrail_output.txt 2>&1 || LINKING_EXIT=$?
        fi
        
        if [[ $LINKING_EXIT -eq 0 ]]; then
            if grep -q "All linking tests PASSED" /tmp/linking_guardrail_output.txt; then
                echo "✓ Linking guardrail PASSED"
            else
                echo "✗ Linking guardrail did not report PASS"
                ERRORS=$((ERRORS + 1))
            fi
        else
            echo "✗ Linking guardrail execution failed (exit code: $LINKING_EXIT)"
            ERRORS=$((ERRORS + 1))
        fi
    else
        echo "✗ verify-linking.sh not found"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo "  SKIPPED: FEATURE_LINKING is not enabled (FEATURE_LINKING=${FEATURE_LINKING_VALUE})"
fi
echo

# Check 3.6: DAO guardrail (if FEATURE_DAO enabled)
echo "Check 3.6: DAO guardrail (if FEATURE_DAO enabled)..."
FEATURE_DAO_ENABLED=false
FEATURE_DAO_VALUE=""

# Read from .env file first (deterministic)
if [[ -f "$ENV_FILE" ]]; then
    FEATURE_DAO_FROM_ENV="$(grep -E "^FEATURE_DAO=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    if [[ -n "$FEATURE_DAO_FROM_ENV" ]]; then
        FEATURE_DAO_VALUE="$FEATURE_DAO_FROM_ENV"
        FEATURE_DAO_NORMALIZED="$(echo "$FEATURE_DAO_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_DAO_NORMALIZED" == "true" ]] || [[ "$FEATURE_DAO_NORMALIZED" == "1" ]] || [[ "$FEATURE_DAO_NORMALIZED" == "yes" ]] || [[ "$FEATURE_DAO_NORMALIZED" == "on" ]]; then
            FEATURE_DAO_ENABLED=true
        fi
    fi
fi

# Fallback to shell env if not in .env
if [[ -z "$FEATURE_DAO_VALUE" ]]; then
    FEATURE_DAO_VALUE="${FEATURE_DAO:-false}"
    if [[ "$FEATURE_DAO_VALUE" == "true" ]] || [[ "$FEATURE_DAO_VALUE" == "1" ]]; then
        FEATURE_DAO_ENABLED=true
    fi
fi

if [[ "$FEATURE_DAO_ENABLED" == "true" ]]; then
    if [[ -f "$SCRIPT_DIR/verify-dao.sh" ]]; then
        DAO_EXIT=0
        if is_container "${1:-}"; then
            cd /var/www/html || exit 1
            FEATURE_DAO=true IMDC_API_BASE_URL=http://web:80 ./scripts/verify-dao.sh --in-container > /tmp/dao_guardrail_output.txt 2>&1 || DAO_EXIT=$?
        elif has_docker_compose; then
            docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && FEATURE_DAO=true IMDC_API_BASE_URL=http://web:80 /var/www/html/scripts/verify-dao.sh --in-container" > /tmp/dao_guardrail_output.txt 2>&1 || DAO_EXIT=$?
        else
            FEATURE_DAO=true "$SCRIPT_DIR/verify-dao.sh" > /tmp/dao_guardrail_output.txt 2>&1 || DAO_EXIT=$?
        fi
        
        if [[ $DAO_EXIT -eq 0 ]]; then
            if grep -q "All DAO tests PASSED" /tmp/dao_guardrail_output.txt; then
                echo "✓ DAO guardrail PASSED"
            else
                echo "✗ DAO guardrail did not report PASS"
                ERRORS=$((ERRORS + 1))
            fi
        else
            echo "✗ DAO guardrail execution failed (exit code: $DAO_EXIT)"
            ERRORS=$((ERRORS + 1))
        fi
    else
        echo "✗ verify-dao.sh not found"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo "  SKIPPED: FEATURE_DAO is not enabled (FEATURE_DAO=${FEATURE_DAO_VALUE})"
fi
echo

# Check 4: No "application" hostname in code (hostname-only; ignore MIME/docs)
echo "Check 4: No 'application' hostname found in code..."

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

# Check 4: DID guardrail must PASS...
echo "Check 4: DID guardrail must PASS..."
if [[ -f "$SCRIPT_DIR/verify-did.sh" ]]; then
    DID_EXIT=0
    if is_container "${1:-}"; then
        # Running inside container: execute directly
        cd /var/www/html || exit 1
        IMDC_API_BASE_URL=http://web:80 ./scripts/verify-did.sh --in-container > /tmp/did_guardrail_output.txt 2>&1 || DID_EXIT=$?
    elif has_docker_compose; then
        # Running on host: use docker compose
        docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && IMDC_API_BASE_URL=http://web:80 /var/www/html/scripts/verify-did.sh --in-container" > /tmp/did_guardrail_output.txt 2>&1 || DID_EXIT=$?
    else
        # No docker compose: try direct execution
        IMDC_API_BASE_URL=http://web:80 "$SCRIPT_DIR/verify-did.sh" > /tmp/did_guardrail_output.txt 2>&1 || DID_EXIT=$?
    fi

    if [[ $DID_EXIT -eq 0 ]]; then
        if grep -q "DID Guardrail PASSED" /tmp/did_guardrail_output.txt; then
            echo "✓ DID guardrail PASSED"
        else
            echo "✗ DID guardrail did not report PASS"
            ERRORS=$((ERRORS + 1))
        fi
    else
        echo "✗ DID guardrail execution failed (exit code: $DID_EXIT)"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo "✗ verify-did.sh not found"
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
