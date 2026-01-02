#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 1

# Helper: Detect if running inside container
is_container() {
    [[ "${1:-}" == "--in-container" ]] && return 0
    [[ -f "/.dockerenv" ]] && return 0
    [[ -f "/proc/self/cgroup" ]] && grep -qE "docker|kubepods" /proc/self/cgroup 2>/dev/null && return 0
    return 1
}

# Helper: Check if docker compose is available
has_docker_compose() {
    command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1
}

# Determine execution context
if is_container "${1:-}"; then
    EXEC_CTX="container"
elif has_docker_compose; then
    echo "=== Reports Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f backend/infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && ./scripts/verify-reports.sh --in-container"
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== Reports Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Check FEATURE_REPORTS from .env file (deterministic detection)
ENV_FILE="${ROOT_DIR}/.env"
SHOULD_SKIP=false

if [[ -f "$ENV_FILE" ]]; then
    FEATURE_REPORTS_FROM_ENV="$(grep -E "^FEATURE_REPORTS=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    if [[ -n "$FEATURE_REPORTS_FROM_ENV" ]]; then
        FEATURE_REPORTS_NORMALIZED="$(echo "$FEATURE_REPORTS_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_REPORTS_NORMALIZED" == "false" ]] || [[ "$FEATURE_REPORTS_NORMALIZED" == "0" ]] || [[ "$FEATURE_REPORTS_NORMALIZED" == "no" ]] || [[ "$FEATURE_REPORTS_NORMALIZED" == "off" ]]; then
            SHOULD_SKIP=true
        fi
    fi
else
    if [[ "${FEATURE_REPORTS:-false}" != "true" ]]; then
        SHOULD_SKIP=true
    fi
fi

if [[ "$SHOULD_SKIP" == "true" ]]; then
    echo "FEATURE_REPORTS is not enabled (FEATURE_REPORTS=${FEATURE_REPORTS_FROM_ENV:-${FEATURE_REPORTS:-false}})"
    echo "SKIP: FEATURE_REPORTS=false"
    exit 0
fi

echo "Guardrail: Temporarily ensuring FEATURE_REPORTS=true for verification..."
echo

export FEATURE_REPORTS=true

ENV_BACKUP=""
TMP_BODY="/tmp/imdc_reports_guardrail_body.$$"
cleanup() {
    rm -f "$TMP_BODY" 2>/dev/null || true
    if [[ -n "$ENV_BACKUP" ]] && [[ -f "$ENV_BACKUP" ]] && [[ -n "$ENV_FILE" ]]; then
        echo "Restoring original .env file..."
        if [[ -f "$ENV_FILE" ]]; then
            mv "$ENV_BACKUP" "$ENV_FILE" 2>/dev/null || true
            php artisan optimize:clear 2>/dev/null || true
            php artisan config:clear 2>/dev/null || true
            php artisan cache:clear 2>/dev/null || true
            php artisan route:clear 2>/dev/null || true
        fi
    fi
}
trap cleanup EXIT

if [[ -f "$ENV_FILE" ]]; then
    TIMESTAMP="$(date +%s)"
    ENV_BACKUP="${ENV_FILE}.bak.verify-reports.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"

    # Ensure FEATURE_REPORTS=true
    FEATURE_REPORTS_CURRENT="$(grep -E "^FEATURE_REPORTS=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    FEATURE_REPORTS_CURRENT_NORMALIZED="$(echo "$FEATURE_REPORTS_CURRENT" | tr '[:upper:]' '[:lower:]' | tr -d ' ' || echo "")"
    
    if [[ "$FEATURE_REPORTS_CURRENT_NORMALIZED" == "true" ]] || [[ "$FEATURE_REPORTS_CURRENT_NORMALIZED" == "1" ]] || [[ "$FEATURE_REPORTS_CURRENT_NORMALIZED" == "yes" ]] || [[ "$FEATURE_REPORTS_CURRENT_NORMALIZED" == "on" ]]; then
        echo "  ✓ FEATURE_REPORTS already enabled in .env"
    else
        if grep -qE "^FEATURE_REPORTS=" "$ENV_FILE" 2>/dev/null; then
            if [[ "$(uname)" == "Darwin" ]]; then
                sed -i '' 's/^FEATURE_REPORTS=.*/FEATURE_REPORTS=true/' "$ENV_FILE"
            else
                sed -i 's/^FEATURE_REPORTS=.*/FEATURE_REPORTS=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_REPORTS=true in .env"
        else
            echo "FEATURE_REPORTS=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_REPORTS=true to .env"
        fi
    fi
else
    echo "  ⚠ .env file not found at ${ENV_FILE}"
    echo "  Guardrail will attempt to run with exported FEATURE_REPORTS=true"
    echo
fi

echo "Pre-flight: Clearing caches after .env modification..."
set +e
php artisan optimize:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
set -e
echo "    ✓ Caches cleared"
echo

# Verify FEATURE_REPORTS is enabled at runtime
echo "Debug: Verifying FEATURE_REPORTS is enabled at runtime..."
if [[ -f "$ENV_FILE" ]]; then
    FEATURE_REPORTS_ENV="$(grep -E '^FEATURE_REPORTS=' "$ENV_FILE" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '\r' || echo '')"
    echo "  FEATURE_REPORTS in .env: ${FEATURE_REPORTS_ENV:-not set}"
else
    echo "  FEATURE_REPORTS in .env: (file not found)"
fi

FEATURE_REPORTS_GETENV="$(php -r 'echo getenv("FEATURE_REPORTS") ?: "NULL";' 2>/dev/null || echo 'unknown')"
echo "  getenv('FEATURE_REPORTS'): ${FEATURE_REPORTS_GETENV}"

FEATURE_REPORTS_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('reports.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
echo "  config('reports.enabled'): ${FEATURE_REPORTS_PHP}"

if [[ "$FEATURE_REPORTS_PHP" != "true" ]]; then
    echo "  ⚠ WARNING: FEATURE_REPORTS is not enabled at runtime!"
    echo "  Clearing caches again and re-checking..."
    set +e
    php artisan optimize:clear 2>/dev/null || true
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    set -e
    FEATURE_REPORTS_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('reports.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
    echo "  config('reports.enabled') after re-clear: ${FEATURE_REPORTS_PHP}"
    if [[ "$FEATURE_REPORTS_PHP" != "true" ]]; then
        echo "  ✗ ERROR: FEATURE_REPORTS still not enabled after cache clear!"
        exit 1
    fi
fi
echo "  ✓ FEATURE_REPORTS confirmed enabled at runtime"
echo

# Determine API base URL
if [[ -n "${IMDC_API_BASE_URL:-}" ]]; then
    API_BASE_URL="${IMDC_API_BASE_URL}"
elif [[ "${EXEC_CTX}" == "container" ]]; then
    API_BASE_URL="http://web:80"
else
    API_BASE_URL="http://127.0.0.1:8080"
fi

echo "API Base URL: ${API_BASE_URL}"
echo

curl_http_code() {
    local url="$1"
    local token="${2:-}"
    local method="${3:-GET}"
    
    local curl_args=(
        -sS
        -o "$TMP_BODY"
        -w "%{http_code}"
        --connect-timeout 2
        --max-time 10
        -H "Accept: application/json"
    )
    
    if [[ -n "$token" ]]; then
        curl_args+=(-H "Authorization: Bearer ${token}")
    fi
    
    if [[ "$method" == "POST" ]] || [[ "$method" == "PUT" ]]; then
        curl_args+=(-X "$method" -H "Content-Type: application/json")
    fi
    
    curl "${curl_args[@]}" "$url" || echo "000000"
}

# Test 1: Mint authentication token (use admin user)
echo "Test 1: Minting authentication token for admin user..."
set +e
TOKEN="$(FEATURE_REPORTS=true php artisan imdc:mint-debug-token --email=admin@imdc.local --database=core 2>/dev/null | tail -n 1 | tr -d "\r\n")"
TOKEN_EXIT=$?
set -e

if [ $TOKEN_EXIT -ne 0 ] || [ -z "$TOKEN" ]; then
    echo "✗ Token mint failed"
    exit 1
fi

echo "✓ Token minted (${#TOKEN} chars)"
echo

# Test 2: Admin health check
echo "Test 2: Admin health check..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/health" "$TOKEN")"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ Admin health check failed (HTTP ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ Admin health check passed (HTTP ${HTTP_CODE})"
echo

# Test 3: System overview report
echo "Test 3: System overview report..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/reports/system-overview" "$TOKEN")"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ System overview report failed (HTTP ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ System overview report passed (HTTP ${HTTP_CODE})"
echo

# Test 4: Guardrail runs report
echo "Test 4: Guardrail runs report..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/reports/guardrail-runs" "$TOKEN")"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ Guardrail runs report failed (HTTP ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ Guardrail runs report passed (HTTP ${HTTP_CODE})"
echo

# Test 5: Audit logs report
echo "Test 5: Audit logs report..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/reports/audit-logs" "$TOKEN")"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ Audit logs report failed (HTTP ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ Audit logs report passed (HTTP ${HTTP_CODE})"
echo

# Test 6: Audit logs with pagination
echo "Test 6: Audit logs with pagination..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/reports/audit-logs?limit=10&offset=0" "$TOKEN")"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ Audit logs pagination failed (HTTP ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ Audit logs pagination passed (HTTP ${HTTP_CODE})"
echo

# Test 7: Validate DB reads (non-destructive)
echo "Test 7: Validating DB reads (non-destructive)..."
set +e
DB_VALIDATION="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$usersCount = \App\Models\User::on('core')->count();
\$rolesCount = \App\Models\Role::on('core')->count();
\$auditLogsCount = \App\Models\AuditLog::on('core')->count();

echo 'PASS: DB reads validated\n';
echo '  Users: ' . \$usersCount . '\n';
echo '  Roles: ' . \$rolesCount . '\n';
echo '  Audit Logs: ' . \$auditLogsCount . '\n';
" 2>&1)"
DB_VALIDATION_EXIT=$?
set -e

if [ $DB_VALIDATION_EXIT -ne 0 ]; then
    echo "✗ Database validation failed"
    echo "$DB_VALIDATION"
    exit 1
fi

echo "$DB_VALIDATION"
echo

echo "✓ All reports tests PASSED"
echo
echo "=== Reports Verification Complete ==="
echo

exit 0
