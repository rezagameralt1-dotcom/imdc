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
    echo "=== DID Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-did.sh --in-container'
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== DID Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Guardrail ALWAYS runs - temporarily enables DID for the duration of the script
echo "Guardrail: Temporarily enabling FEATURE_DID for verification..."
echo

# Export FEATURE_DID=true for all child processes (artisan, curl, etc.)
export FEATURE_DID=true

# Backup and modify .env file so php-fpm workers can read FEATURE_DID=true
ENV_FILE="${ROOT_DIR}/.env"
ENV_BACKUP=""

# Initialize cleanup function early (before .env modification)
TMP_BODY="/tmp/imdc_did_guardrail_body.$$"
cleanup() {
    rm -f "$TMP_BODY" 2>/dev/null || true
    # Restore .env if it was backed up
    if [[ -n "$ENV_BACKUP" ]] && [[ -f "$ENV_BACKUP" ]] && [[ -n "$ENV_FILE" ]]; then
        echo "Restoring original .env file..."
        if [[ -f "$ENV_FILE" ]]; then
            mv "$ENV_BACKUP" "$ENV_FILE" 2>/dev/null || true
            # Clear caches after restore
            php artisan config:clear 2>/dev/null || true
            php artisan cache:clear 2>/dev/null || true
        fi
    fi
}
trap cleanup EXIT

if [[ -f "$ENV_FILE" ]]; then
    TIMESTAMP="$(date +%s)"
    ENV_BACKUP="${ENV_FILE}.bak.verify-did.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"
    
    # Ensure FEATURE_DID=true is set in .env
    if grep -qE "^FEATURE_DID=" "$ENV_FILE" 2>/dev/null; then
        # Replace existing line
        if [[ "$(uname)" == "Darwin" ]]; then
            # macOS sed
            sed -i '' 's/^FEATURE_DID=.*/FEATURE_DID=true/' "$ENV_FILE"
        else
            # Linux sed
            sed -i 's/^FEATURE_DID=.*/FEATURE_DID=true/' "$ENV_FILE"
        fi
        echo "  ✓ Updated FEATURE_DID=true in .env"
    else
        # Append if not exists
        echo "FEATURE_DID=true" >> "$ENV_FILE"
        echo "  ✓ Added FEATURE_DID=true to .env"
    fi
else
    echo "  ⚠ .env file not found at ${ENV_FILE}"
    echo "  Guardrail will attempt to run with exported FEATURE_DID=true"
    echo "  Note: php-fpm workers may not see the env variable without .env file"
    echo
fi

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

# Pre-flight: Clear caches to ensure FEATURE_DID from .env is read at runtime
echo "Pre-flight: Clearing caches after .env modification..."
echo
set +e
php artisan optimize:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
set -e
echo "    ✓ Caches cleared (config, cache, route)"
echo

# Verify FEATURE_DID is enabled at runtime before API calls
echo "Debug: Verifying FEATURE_DID is enabled at runtime..."
if [[ -f "$ENV_FILE" ]]; then
    FEATURE_DID_ENV="$(grep -E '^FEATURE_DID=' "$ENV_FILE" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '\r' || echo '')"
    echo "  FEATURE_DID in .env: ${FEATURE_DID_ENV:-not set}"
else
    echo "  FEATURE_DID in .env: (file not found)"
fi

FEATURE_DID_GETENV="$(php -r 'echo getenv("FEATURE_DID") ?: "NULL";' 2>/dev/null || echo 'unknown')"
echo "  getenv('FEATURE_DID'): ${FEATURE_DID_GETENV}"

FEATURE_DID_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('did.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
echo "  config('did.enabled'): ${FEATURE_DID_PHP}"

if [[ "$FEATURE_DID_PHP" != "true" ]]; then
    echo "  ⚠ WARNING: FEATURE_DID is not enabled at runtime!"
    echo "  Clearing caches again and re-checking..."
    set +e
    php artisan optimize:clear 2>/dev/null || true
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    set -e
    FEATURE_DID_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('did.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
    echo "  config('did.enabled') after re-clear: ${FEATURE_DID_PHP}"
    if [[ "$FEATURE_DID_PHP" != "true" ]]; then
        echo "  ✗ ERROR: FEATURE_DID still not enabled after cache clear!"
        exit 1
    fi
fi
echo "  ✓ FEATURE_DID confirmed enabled at runtime"
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

# Pre-flight: Clear caches to ensure FEATURE_DID from .env is read at runtime
echo "Pre-flight: Clearing caches to ensure FEATURE_DID from .env is read at runtime..."
echo
set +e
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
set -e
echo "    ✓ Caches cleared"
echo

# Debug: Show effective FEATURE_DID value from .env
echo "Debug: Checking FEATURE_DID from .env file..."
if [[ -f "$ENV_FILE" ]]; then
    FEATURE_DID_FROM_ENV="$(grep -E "^FEATURE_DID=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "not found")"
    echo "  FEATURE_DID from .env: ${FEATURE_DID_FROM_ENV}"
else
    echo "  .env file not found"
fi
echo

# Pre-flight: Run core migrations (for did_profiles table)
echo "Pre-flight: Running core migrations..."
echo
set +e
CORE_MIGRATE_OUTPUT="$(FEATURE_DID=true php artisan migrate --force --database=core --path=database/migrations/core 2>&1)"
CORE_MIGRATE_EXIT=$?
set -e
if [[ $CORE_MIGRATE_EXIT -ne 0 ]]; then
    echo "✗ Core migration failed:"
    echo "$CORE_MIGRATE_OUTPUT" | head -20
    exit 1
fi
echo "    ✓ Core migrations complete"
echo

# Pre-flight: Clear route cache and verify DID routes exist
echo "Pre-flight: Clearing route cache and verifying DID routes are registered..."
set +e
FEATURE_DID=true php artisan route:clear 2>/dev/null || true
ROUTE_CHECK="$(FEATURE_DID=true php artisan route:list --path=api/v1/did/me 2>&1)"
ROUTE_CHECK_EXIT=$?
set -e
if [[ $ROUTE_CHECK_EXIT -ne 0 ]] || ! echo "$ROUTE_CHECK" | grep -qE "(did/me|did\.me)"; then
    echo "✗ DID routes are not registered"
    echo "  Expected route: GET/POST/PUT api/v1/did/me"
    echo "  Route list output:"
    echo "$ROUTE_CHECK" | head -20
    echo ""
    echo "  ERROR: DID routes must be registered in routes/api.php"
    echo "  Check that DidMeController is imported and routes are defined."
    exit 1
fi

# Verify all three methods are present
if ! echo "$ROUTE_CHECK" | grep -qE "GET.*did"; then
    echo "✗ GET /api/v1/did/me route not found"
    exit 1
fi
if ! echo "$ROUTE_CHECK" | grep -qE "POST.*did"; then
    echo "✗ POST /api/v1/did/me route not found"
    exit 1
fi
if ! echo "$ROUTE_CHECK" | grep -qE "PUT.*did"; then
    echo "✗ PUT /api/v1/did/me route not found"
    exit 1
fi

echo "    ✓ DID routes registered (GET, POST, PUT)"
echo

# Test 1: Mint authentication token (use admin user)
echo "Test 1: Minting authentication token for admin user..."
set +e
TOKEN="$(FEATURE_DID=true php artisan imdc:mint-debug-token --email=admin@imdc.local --database=core 2>/dev/null | tail -n 1 | tr -d "\r\n")"
TOKEN_EXIT=$?
set -e

if [ $TOKEN_EXIT -ne 0 ] || [ -z "$TOKEN" ]; then
    echo "✗ Token mint failed"
    exit 1
fi

echo "✓ Token minted (${#TOKEN} chars)"
echo

# Helper: Extract JSON value using jq if available, otherwise grep/sed fallback
extract_json_value() {
    local json="$1"
    local path="$2"
    
    # Try jq first if available
    if command -v jq >/dev/null 2>&1; then
        jq -r "$path // empty" <<< "$json" 2>/dev/null | grep -v '^null$' | head -1
        return
    fi
    
    # Fallback: grep/sed for common patterns
    if [[ "$path" == ".data.id" ]] || [[ "$path" == ".data.id // .id" ]]; then
        local value="$(echo "$json" | grep -oE '"data"[[:space:]]*:[[:space:]]*\{[^}]*"id"[[:space:]]*:[[:space:]]*("([^"]+)"|[0-9]+)' | sed -nE 's/.*"id"[[:space:]]*:[[:space:]]*("([^"]+)"|([0-9]+)).*/\2\3/p' | head -1)"
        if [[ -n "$value" ]]; then
            echo "$value"
            return
        fi
        value="$(echo "$json" | grep -oE '"id"[[:space:]]*:[[:space:]]*("([^"]+)"|[0-9]+)' | sed -nE 's/"id"[[:space:]]*:[[:space:]]*("([^"]+)"|([0-9]+))/\2\3/p' | head -1)"
        if [[ -n "$value" ]]; then
            echo "$value"
            return
        fi
    fi
    
    if [[ "$path" == ".id" ]]; then
        echo "$json" | grep -oE '"id"[[:space:]]*:[[:space:]]*("([^"]+)"|[0-9]+)' | sed -nE 's/"id"[[:space:]]*:[[:space:]]*("([^"]+)"|([0-9]+))/\2\3/p' | head -1
        return
    fi
    
    echo ""
}

# Get current user ID
echo "Test 2: Getting current user ID..."
set +e
USER_RESPONSE="$(curl_http_code "${API_BASE_URL}/api/v1/me" "$TOKEN")"
USER_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "${USER_RESPONSE}" != "200" ]]; then
    echo "✗ Could not get user info (HTTP ${USER_RESPONSE})"
    echo "Response body:"
    echo "$USER_BODY" | head -20
    exit 1
fi

USER_ID="$(extract_json_value "$USER_BODY" ".data.id // .id")"

if [[ -z "$USER_ID" ]]; then
    echo "✗ Could not extract user ID from response"
    echo "Full JSON response:"
    echo "$USER_BODY"
    exit 1
fi

echo "✓ Current user ID: ${USER_ID}"
echo

# Test 3: Create DID profile (POST /api/v1/did/me)
echo "Test 3: Creating DID profile..."
DID_PAYLOAD="{\"display_name\":\"Test User DID\",\"wallet_address\":\"0x1234567890abcdef\",\"metadata_json\":{\"test\":\"value\"}}"

set +e
CREATE_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/did/me" "$TOKEN" "POST" "${DID_PAYLOAD}")"
CREATE_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "${CREATE_CODE}" != "201" ]]; then
    echo "✗ DID profile creation failed (HTTP ${CREATE_CODE})"
    echo "$CREATE_BODY" | head -20
    exit 1
fi

# Extract DID from response
DID_VALUE="$(echo "$CREATE_BODY" | grep -o '"did":"[^"]*"' | head -1 | sed 's/"did":"\([^"]*\)"/\1/' || echo '')"
if [[ -z "$DID_VALUE" ]]; then
    echo "✗ Could not extract DID from creation response"
    exit 1
fi

echo "✓ DID profile created: ${DID_VALUE}"
echo

# Test 4: Get DID profile (GET /api/v1/did/me)
echo "Test 4: Getting DID profile..."
set +e
GET_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/did/me" "$TOKEN")"
GET_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "${GET_CODE}" != "200" ]]; then
    echo "✗ DID profile retrieval failed (HTTP ${GET_CODE})"
    echo "$GET_BODY" | head -20
    exit 1
fi

GET_DID="$(echo "$GET_BODY" | grep -o '"did":"[^"]*"' | head -1 | sed 's/"did":"\([^"]*\)"/\1/' || echo '')"
if [[ "$GET_DID" != "$DID_VALUE" ]]; then
    echo "✗ DID mismatch: expected ${DID_VALUE}, got ${GET_DID}"
    exit 1
fi

echo "✓ DID profile retrieved: ${GET_DID}"
echo

# Test 5: Update DID profile (PUT /api/v1/did/me)
echo "Test 5: Updating DID profile..."
UPDATE_PAYLOAD="{\"display_name\":\"Updated DID Name\",\"wallet_address\":\"0xupdated123\"}"

set +e
UPDATE_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/did/me" "$TOKEN" "PUT" "${UPDATE_PAYLOAD}")"
UPDATE_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "${UPDATE_CODE}" != "200" ]]; then
    echo "✗ DID profile update failed (HTTP ${UPDATE_CODE})"
    echo "$UPDATE_BODY" | head -20
    exit 1
fi

UPDATED_NAME="$(echo "$UPDATE_BODY" | grep -o '"display_name":"[^"]*"' | head -1 | sed 's/"display_name":"\([^"]*\)"/\1/' || echo '')"
if [[ "$UPDATED_NAME" != "Updated DID Name" ]]; then
    echo "✗ Display name update failed: expected 'Updated DID Name', got '${UPDATED_NAME}'"
    exit 1
fi

echo "✓ DID profile updated: display_name=${UPDATED_NAME}"
echo

# Test 6: Verify database write
echo "Test 6: Verifying database write..."
set +e
DB_CHECK="$(FEATURE_DID=true php "$ROOT_DIR/scripts/_guardrail/verify_did_profile.php" "${USER_ID}" "${DID_VALUE}" 2>/dev/null | tail -1)"
set -e

if [[ "$DB_CHECK" != "ok" ]]; then
    echo "✗ Database verification failed: ${DB_CHECK}"
    exit 1
fi

echo "✓ Database write verified"
echo

echo "=== DID Guardrail PASSED ==="
exit 0
