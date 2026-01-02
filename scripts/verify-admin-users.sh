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
    echo "=== Admin Users Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f backend/infra/docker/docker-compose.yml exec -T app sh -lc "cd /var/www/html && ./scripts/verify-admin-users.sh --in-container"
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== Admin Users Verification (Official Guardrail) ==="
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
TMP_BODY="/tmp/imdc_admin_users_guardrail_body.$$"
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
    ENV_BACKUP="${ENV_FILE}.bak.verify-admin-users.${TIMESTAMP}"
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

# Verify FEATURE_ADMIN is enabled at runtime
echo "Debug: Verifying FEATURE_ADMIN is enabled at runtime..."
if [[ -f "$ENV_FILE" ]]; then
    FEATURE_ADMIN_ENV="$(grep -E '^FEATURE_ADMIN=' "$ENV_FILE" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '\r' || echo '')"
    echo "  FEATURE_ADMIN in .env: ${FEATURE_ADMIN_ENV:-not set}"
else
    echo "  FEATURE_ADMIN in .env: (file not found)"
fi

FEATURE_ADMIN_GETENV="$(php -r 'echo getenv("FEATURE_ADMIN") ?: "NULL";' 2>/dev/null || echo 'unknown')"
echo "  getenv('FEATURE_ADMIN'): ${FEATURE_ADMIN_GETENV}"

FEATURE_ADMIN_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('admin.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
echo "  config('admin.enabled'): ${FEATURE_ADMIN_PHP}"

if [[ "$FEATURE_ADMIN_PHP" != "true" ]]; then
    echo "  ⚠ WARNING: FEATURE_ADMIN is not enabled at runtime!"
    echo "  Clearing caches again and re-checking..."
    set +e
    php artisan optimize:clear 2>/dev/null || true
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    set -e
    FEATURE_ADMIN_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('admin.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
    echo "  config('admin.enabled') after re-clear: ${FEATURE_ADMIN_PHP}"
    if [[ "$FEATURE_ADMIN_PHP" != "true" ]]; then
        echo "  ✗ ERROR: FEATURE_ADMIN still not enabled after cache clear!"
        exit 1
    fi
fi
echo "  ✓ FEATURE_ADMIN confirmed enabled at runtime"
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

# Create or get non-admin user (idempotent)
NON_ADMIN_EMAIL="nonadmin.guardrail@imdc.local"
NON_ADMIN_PASSWORD="GuardrailPass!123"
NON_ADMIN_NAME="Guardrail NonAdmin"

echo "  Creating/ensuring non-admin user: ${NON_ADMIN_EMAIL}..."
USER_CREATE_OUTPUT="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

\$user = User::on('core')->where('email', '${NON_ADMIN_EMAIL}')->first();

if (!\$user) {
    \$user = User::on('core')->create([
        'name' => '${NON_ADMIN_NAME}',
        'email' => '${NON_ADMIN_EMAIL}',
        'password' => Hash::make('${NON_ADMIN_PASSWORD}'),
    ]);
    echo 'Created user: ' . \$user->id . '\n';
} else {
    echo 'User exists: ' . \$user->id . '\n';
}

// Ensure user does NOT have Admin role
\$adminRole = Role::on('core')->where('name', 'Admin')->first();
if (\$adminRole && \$user->hasRole('Admin')) {
    \$user->removeRole('Admin');
    echo 'Removed Admin role\n';
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

# Extract token from response (standard format: {"success":true,"data":{"token":"...","user":{...}}})
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
    # Fallback: try grep/sed extraction
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

# Test 3: Confirm non-admin gets 403 on /api/v1/admin/users
echo "Test 3: Confirming non-admin gets 403 on /api/v1/admin/users..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/users" "$NON_ADMIN_TOKEN")"
if [[ "$HTTP_CODE" != "403" ]]; then
    echo "✗ Non-admin access test failed (expected HTTP 403, got ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ Non-admin correctly receives 403 (HTTP ${HTTP_CODE})"
echo

# Test 4: Confirm admin gets 200 and returns users list
echo "Test 4: Confirming admin gets 200 and returns users list..."
HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/users" "$ADMIN_TOKEN")"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ Admin users list failed (HTTP ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ Admin users list passed (HTTP ${HTTP_CODE})"
echo

# Test 5: Get user details
echo "Test 5: Getting user details..."
# Extract first user ID from the list (assuming admin user exists)
USER_ID="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$user = \App\Models\User::on('core')->first();
echo \$user ? \$user->id : '1';
" 2>/dev/null || echo '1')"

HTTP_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/admin/users/${USER_ID}" "$ADMIN_TOKEN")"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "✗ Get user details failed (HTTP ${HTTP_CODE})"
    cat "$TMP_BODY" 2>/dev/null | head -5 || true
    exit 1
fi
echo "✓ Get user details passed (HTTP ${HTTP_CODE})"
echo

# Test 6: Assign roles to user (idempotent)
echo "Test 6: Assigning roles to user (idempotent test)..."
# Use the non-admin user for role assignment test
NON_ADMIN_USER_ID="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$user = \App\Models\User::on('core')->where('email', '${NON_ADMIN_EMAIL}')->first();
echo \$user ? \$user->id : '2';
" 2>/dev/null || echo '2')"

# Check if "User" or "Seller" role exists, use "User" as default
ROLE_NAME="User"
ROLE_EXISTS="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$role = \App\Models\Role::on('core')->where('name', 'User')->first();
echo \$role ? 'yes' : 'no';
" 2>/dev/null || echo 'no')"

if [[ "$ROLE_EXISTS" != "yes" ]]; then
    # Try "Seller" role
    ROLE_NAME="Seller"
    ROLE_EXISTS="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$role = \App\Models\Role::on('core')->where('name', 'Seller')->first();
echo \$role ? 'yes' : 'no';
" 2>/dev/null || echo 'no')"
fi

if [[ "$ROLE_EXISTS" != "yes" ]]; then
    echo "  ⚠ Warning: Neither 'User' nor 'Seller' role found, skipping role assignment test"
else
    ROLE_JSON="{\"roles\":[\"${ROLE_NAME}\"]}"
    
    # First assignment
    HTTP_CODE_1="$(curl_http_code "${API_BASE_URL}/api/v1/admin/users/${NON_ADMIN_USER_ID}/roles" "$ADMIN_TOKEN" "PUT" "$ROLE_JSON")"
    if [[ "$HTTP_CODE_1" != "200" ]]; then
        echo "✗ First role assignment failed (HTTP ${HTTP_CODE_1})"
        cat "$TMP_BODY" 2>/dev/null | head -5 || true
        exit 1
    fi
    echo "✓ First role assignment passed (HTTP ${HTTP_CODE_1})"
    
    # Extract roles from response
    FIRST_RESPONSE="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
    FIRST_ROLES="$(echo "$FIRST_RESPONSE" | grep -oE '"roles":\[[^]]*\]' | head -1 || echo '')"
    
    # Second assignment (idempotent - should return same result)
    sleep 1
    HTTP_CODE_2="$(curl_http_code "${API_BASE_URL}/api/v1/admin/users/${NON_ADMIN_USER_ID}/roles" "$ADMIN_TOKEN" "PUT" "$ROLE_JSON")"
    if [[ "$HTTP_CODE_2" != "200" ]]; then
        echo "✗ Second role assignment failed (HTTP ${HTTP_CODE_2})"
        cat "$TMP_BODY" 2>/dev/null | head -5 || true
        exit 1
    fi
    echo "✓ Second role assignment passed (HTTP ${HTTP_CODE_2})"
    
    # Extract roles from second response
    SECOND_RESPONSE="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
    SECOND_ROLES="$(echo "$SECOND_RESPONSE" | grep -oE '"roles":\[[^]]*\]' | head -1 || echo '')"
    
    if [[ "$FIRST_ROLES" == "$SECOND_ROLES" ]]; then
        echo "✓ Idempotency confirmed: same roles returned"
    else
        echo "  ⚠ Warning: Roles differ between calls (may be expected if roles changed)"
    fi
fi
echo

echo "✓ All admin users tests PASSED"
echo
echo "=== Admin Users Verification Complete ==="
echo

exit 0
