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
    echo "=== Admin Dashboard Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f backend/infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && ./scripts/verify-admin-dashboard.sh --in-container"
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== Admin Dashboard Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Check FEATURE_ADMIN from .env file (deterministic detection)
ENV_FILE="${ROOT_DIR}/.env"
SHOULD_SKIP=false

if [[ -f "$ENV_FILE" ]]; then
    FEATURE_ADMIN_FROM_ENV="$(grep -E "^FEATURE_ADMIN=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    if [[ -n "$FEATURE_ADMIN_FROM_ENV" ]]; then
        FEATURE_ADMIN_NORMALIZED="$(echo "$FEATURE_ADMIN_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_ADMIN_NORMALIZED" == "false" ]] || [[ "$FEATURE_ADMIN_NORMALIZED" == "0" ]] || [[ "$FEATURE_ADMIN_NORMALIZED" == "no" ]] || [[ "$FEATURE_ADMIN_NORMALIZED" == "off" ]]; then
            SHOULD_SKIP=true
        fi
    fi
else
    if [[ "${FEATURE_ADMIN:-false}" != "true" ]]; then
        SHOULD_SKIP=true
    fi
fi

if [[ "$SHOULD_SKIP" == "true" ]]; then
    echo "FEATURE_ADMIN is not enabled (FEATURE_ADMIN=${FEATURE_ADMIN_FROM_ENV:-${FEATURE_ADMIN:-false}})"
    echo "SKIP: FEATURE_ADMIN=false"
    exit 0
fi

echo "Guardrail: Temporarily ensuring FEATURE_ADMIN=true and FEATURE_REPORTS=true for verification..."
echo

export FEATURE_ADMIN=true
export FEATURE_REPORTS=true

ENV_BACKUP=""
TMP_BODY="/tmp/imdc_admin_dashboard_guardrail_body.$$"
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
    ENV_BACKUP="${ENV_FILE}.bak.verify-admin-dashboard.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"

    # Ensure FEATURE_ADMIN=true
    FEATURE_ADMIN_CURRENT="$(grep -E "^FEATURE_ADMIN=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    FEATURE_ADMIN_CURRENT_NORMALIZED="$(echo "$FEATURE_ADMIN_CURRENT" | tr '[:upper:]' '[:lower:]' | tr -d ' ' || echo "")"
    
    if [[ "$FEATURE_ADMIN_CURRENT_NORMALIZED" == "true" ]] || [[ "$FEATURE_ADMIN_CURRENT_NORMALIZED" == "1" ]] || [[ "$FEATURE_ADMIN_CURRENT_NORMALIZED" == "yes" ]] || [[ "$FEATURE_ADMIN_CURRENT_NORMALIZED" == "on" ]]; then
        echo "  ✓ FEATURE_ADMIN already enabled in .env"
    else
        if grep -qE "^FEATURE_ADMIN=" "$ENV_FILE" 2>/dev/null; then
            if [[ "$(uname)" == "Darwin" ]]; then
                sed -i '' 's/^FEATURE_ADMIN=.*/FEATURE_ADMIN=true/' "$ENV_FILE"
            else
                sed -i 's/^FEATURE_ADMIN=.*/FEATURE_ADMIN=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_ADMIN=true in .env"
        else
            echo "FEATURE_ADMIN=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_ADMIN=true to .env"
        fi
    fi

    # Ensure FEATURE_REPORTS=true
    if ! grep -qE "^FEATURE_REPORTS=true" "$ENV_FILE" 2>/dev/null; then
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
    else
        echo "  ✓ FEATURE_REPORTS already enabled in .env"
    fi
else
    echo "  ⚠ .env file not found at ${ENV_FILE}"
    echo "  Guardrail will attempt to run with exported FEATURE_ADMIN=true and FEATURE_REPORTS=true"
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

# Pre-flight: Run core migrations to ensure schema is up to date
echo "Pre-flight: Running core migrations..."
set +e
php artisan migrate --force --database=core --path=database/migrations/core 2>&1 | grep -E "(DONE|FAIL|ERROR|Nothing)" || true
MIGRATE_EXIT=$?
set -e
if [ $MIGRATE_EXIT -ne 0 ]; then
    echo "  ⚠ Migration warnings (may be expected if already applied)"
else
    echo "    ✓ Migrations completed"
fi
echo

# Pre-flight: Seed baseline permissions
echo "Pre-flight: Seeding baseline admin permissions..."
set +e
php artisan imdc:seed-admin-permissions > /tmp/admin_permissions_seed_output.txt 2>&1
SEED_EXIT=$?
set -e
if [ $SEED_EXIT -ne 0 ]; then
    echo "✗ Failed to seed admin permissions"
    cat /tmp/admin_permissions_seed_output.txt 2>/dev/null || true
    exit 1
fi
echo "✓ Baseline permissions seeded"
echo

# Verify FEATURE_ADMIN and FEATURE_REPORTS are enabled at runtime
echo "Debug: Verifying feature flags are enabled at runtime..."
FEATURE_ADMIN_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('admin.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
FEATURE_REPORTS_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('reports.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"

if [[ "$FEATURE_ADMIN_PHP" != "true" ]] || [[ "$FEATURE_REPORTS_PHP" != "true" ]]; then
    echo "  ⚠ WARNING: Feature flags not enabled at runtime!"
    echo "  FEATURE_ADMIN: ${FEATURE_ADMIN_PHP}, FEATURE_REPORTS: ${FEATURE_REPORTS_PHP}"
    echo "  Clearing caches again and re-checking..."
    set +e
    php artisan optimize:clear 2>/dev/null || true
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    set -e
    FEATURE_ADMIN_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('admin.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
    FEATURE_REPORTS_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('reports.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
    if [[ "$FEATURE_ADMIN_PHP" != "true" ]] || [[ "$FEATURE_REPORTS_PHP" != "true" ]]; then
        echo "  ✗ ERROR: Feature flags still not enabled after cache clear!"
        exit 1
    fi
fi
echo "  ✓ FEATURE_ADMIN and FEATURE_REPORTS confirmed enabled at runtime"
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
    local data="${4:-}"
    
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
        if [[ -n "$data" ]]; then
            curl_args+=(-d "$data")
        fi
    fi
    
    curl "${curl_args[@]}" "$url" || echo "000000"
}

# Test 1: Mint admin token
echo "Test 1: Minting authentication token for admin user..."
set +e
ADMIN_TOKEN="$(FEATURE_ADMIN=true FEATURE_REPORTS=true php artisan imdc:mint-debug-token --email=admin@imdc.local --database=core 2>/dev/null | tail -n 1 | tr -d "\r\n")"
TOKEN_EXIT=$?
set -e

if [ $TOKEN_EXIT -ne 0 ] || [ -z "$ADMIN_TOKEN" ]; then
    echo "✗ Admin token mint failed"
    exit 1
fi

echo "✓ Admin token minted (${#ADMIN_TOKEN} chars)"
echo

# Test 2: Ensure non-admin user exists and mint token
echo "Test 2: Ensuring non-admin user exists and minting token..."
set +e

NON_ADMIN_EMAIL="nonadmin.guardrail@imdc.local"
NON_ADMIN_PASSWORD="GuardrailPass!123"
NON_ADMIN_NAME="Guardrail NonAdmin"

echo "  Creating/ensuring non-admin user: ${NON_ADMIN_EMAIL}..."
USER_CREATE_OUTPUT="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

\$user = User::on('core')->where('email', '${NON_ADMIN_EMAIL}')->first();

if (!\$user) {
    \$user = User::on('core')->create([
        'name' => '${NON_ADMIN_NAME}',
        'email' => '${NON_ADMIN_EMAIL}',
        'password' => Hash::make('${NON_ADMIN_PASSWORD}'),
    ]);
    echo 'Created user: ' . \$user->id . '\n';
} else {
    \$user->password = Hash::make('${NON_ADMIN_PASSWORD}');
    \$user->save();
    echo 'User exists, password updated: ' . \$user->id . '\n';
}

\$adminRoleId = DB::connection('core')->table('roles')->where('name', 'Admin')->value('id');
if (\$adminRoleId) {
    DB::connection('core')->table('model_has_roles')
        ->where('model_type', 'App\\\\Models\\\\User')
        ->where('model_id', \$user->id)
        ->where('role_id', \$adminRoleId)
        ->delete();
    echo 'Removed Admin role (if present)\n';
}

echo 'User ID: ' . \$user->id . '\n';
" 2>&1)"

if [ $? -ne 0 ]; then
    echo "✗ Failed to create/ensure non-admin user"
    echo "$USER_CREATE_OUTPUT"
    exit 1
fi

echo "$USER_CREATE_OUTPUT"

# Mint token via login endpoint
echo "  Logging in to get non-admin token..."
LOGIN_RESPONSE="$(curl -sS -w "\n%{http_code}" -X POST "${API_BASE_URL}/api/v1/auth/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"email\":\"${NON_ADMIN_EMAIL}\",\"password\":\"${NON_ADMIN_PASSWORD}\"}" \
    -o "$TMP_BODY" 2>&1 || echo "000000")"

HTTP_CODE="$(echo "$LOGIN_RESPONSE" | tail -n 1)"
RESPONSE_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"

if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ Non-admin login failed (HTTP ${HTTP_CODE})"
    echo "  Response body:"
    echo "$RESPONSE_BODY" | head -10
    exit 1
fi

# Extract token from response
NON_ADMIN_TOKEN="$(echo "$RESPONSE_BODY" | php -r "
require 'vendor/autoload.php';
\$data = json_decode(file_get_contents('php://stdin'), true);
if (isset(\$data['data']['token'])) {
    echo \$data['data']['token'];
} elseif (isset(\$data['token'])) {
    echo \$data['token'];
} else {
    echo '';
}
" 2>/dev/null || echo '')"

if [ -z "$NON_ADMIN_TOKEN" ]; then
    NON_ADMIN_TOKEN="$(echo "$RESPONSE_BODY" | grep -oE '"data"[^}]*"token"[[:space:]]*:[[:space:]]*"([^"]+)"' | sed -nE 's/.*"token"[[:space:]]*:[[:space:]]*"([^"]+)".*/\1/p' | head -1 || echo '')"
fi

if [ -z "$NON_ADMIN_TOKEN" ]; then
    echo "✗ Failed to extract token from login response"
    echo "  Response body:"
    echo "$RESPONSE_BODY" | head -20
    exit 1
fi

echo "✓ Non-admin token obtained (${#NON_ADMIN_TOKEN} chars)"
echo

# Test 3: Confirm non-admin gets 403 on dashboard endpoint
echo "Test 3: Confirming non-admin gets 403 on dashboard endpoint..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/dashboard/overview" "$NON_ADMIN_TOKEN")"
if [[ "$HTTP_CODE" != "403" ]]; then
    echo "✗ Non-admin access test failed (expected HTTP 403, got ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ Non-admin correctly receives 403 on dashboard endpoint"
echo

# Test 4: Admin can access dashboard overview
echo "Test 4: Admin accessing dashboard overview..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/dashboard/overview" "$ADMIN_TOKEN")"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ Admin dashboard overview failed (HTTP ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi

# Validate response structure
DASHBOARD_RESPONSE="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
HAS_SYSTEM_OVERVIEW="$(echo "$DASHBOARD_RESPONSE" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo isset(\$data['data']['system_overview']) ? 'yes' : 'no';" 2>/dev/null || echo 'no')"
HAS_GUARDRAIL_RUNS="$(echo "$DASHBOARD_RESPONSE" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo isset(\$data['data']['recent_guardrail_runs']) ? 'yes' : 'no';" 2>/dev/null || echo 'no')"
HAS_AUDIT_LOGS="$(echo "$DASHBOARD_RESPONSE" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo isset(\$data['data']['audit_logs_summary']) ? 'yes' : 'no';" 2>/dev/null || echo 'no')"
HAS_RBAC_STATS="$(echo "$DASHBOARD_RESPONSE" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo isset(\$data['data']['rbac_stats']) ? 'yes' : 'no';" 2>/dev/null || echo 'no')"

if [[ "$HAS_SYSTEM_OVERVIEW" != "yes" ]]; then
    echo "✗ Dashboard response missing 'system_overview' key"
    exit 1
fi
if [[ "$HAS_GUARDRAIL_RUNS" != "yes" ]]; then
    echo "✗ Dashboard response missing 'recent_guardrail_runs' key"
    exit 1
fi
if [[ "$HAS_AUDIT_LOGS" != "yes" ]]; then
    echo "✗ Dashboard response missing 'audit_logs_summary' key"
    exit 1
fi
if [[ "$HAS_RBAC_STATS" != "yes" ]]; then
    echo "✗ Dashboard response missing 'rbac_stats' key"
    exit 1
fi

echo "✓ Admin dashboard overview passed (HTTP ${HTTP_CODE})"
echo "✓ Response structure validated (all required keys present)"
echo

echo "✓ All admin dashboard tests PASSED"
echo
echo "=== Admin Dashboard Verification Complete ==="
echo

exit 0
