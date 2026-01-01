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
    echo "=== NFT Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-nft.sh --in-container'
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== NFT Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Check FEATURE_NFT flag
if [[ "${FEATURE_NFT:-false}" != "true" ]]; then
    echo "FEATURE_NFT is not enabled (FEATURE_NFT=${FEATURE_NFT:-false})"
    echo "SKIPPED: NFT verification"
    exit 0
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

TMP_BODY="/tmp/imdc_nft_guardrail_body.$$"
cleanup() { rm -f "$TMP_BODY" 2>/dev/null || true; }
trap cleanup EXIT

curl_http_code() {
    local url="$1"
    local token="${2:-}"
    local method="${3:-GET}"
    local data="${4:-}"
    local idempotency_key="${5:-}"
    
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
    
    if [[ -n "$idempotency_key" ]]; then
        curl_args+=(-H "Idempotency-Key: ${idempotency_key}")
    fi
    
    if [[ "$method" == "POST" ]] || [[ "$method" == "PUT" ]] || [[ "$method" == "PATCH" ]]; then
        curl_args+=(-X "$method" -H "Content-Type: application/json")
        if [[ -n "$data" ]]; then
            curl_args+=(-d "$data")
        fi
    fi
    
    curl "${curl_args[@]}" "$url" || echo "000000"
}

# Guardrail clean mode: Reset nfts database for deterministic testing
# This ensures WORM chain verification only checks logs from the current run
GUARDRAIL_CLEAN="${IMDC_GUARDRAIL:-1}"
if [[ "${GUARDRAIL_CLEAN}" == "1" ]] || [[ "${IMDC_NFT_GUARDRAIL_CLEAN:-0}" == "1" ]]; then
    echo "Guardrail clean mode: resetting nfts database via migrate:fresh"
    echo
    set +e
    php artisan migrate:fresh --database=nfts --path=database/migrations/nfts --force 2>&1 | grep -E "(DONE|FAIL|ERROR)" || true
    MIGRATE_EXIT=$?
    set -e
    if [[ $MIGRATE_EXIT -ne 0 ]]; then
        echo "✗ Failed to reset nfts database"
        exit 1
    fi
    echo "✓ NFTs database reset complete"
    echo
else
    echo "Guardrail clean mode: OFF"
    echo
fi

# Pre-flight: Wait for Postgres readiness and ensure database exists (self-healing)
echo "Pre-flight: Ensuring Postgres readiness and nfts database exists..."
echo

# Get database connection details from environment or config defaults
DB_HOST="${DB_NFTS_HOST:-${DB_HOST:-db}}"
DB_PORT="${DB_NFTS_PORT:-${DB_PORT:-5432}}"
DB_USER="${DB_NFTS_USERNAME:-${DB_USERNAME:-imdc}}"
DB_PASS="${DB_NFTS_PASSWORD:-${DB_PASSWORD:-imdc}}"
DB_NAME="imdc_nfts"
# Get POSTGRES_USER from container environment or use default from docker-compose.yml
if has_docker_compose && [[ "${EXEC_CTX}" != "container" ]]; then
    POSTGRES_USER="$(docker compose -f infra/docker/docker-compose.yml exec -T db sh -c 'echo "$POSTGRES_USER"' 2>/dev/null | tr -d '\r\n' || echo "imdc")"
else
    POSTGRES_USER="${POSTGRES_USER:-${DB_USER}}"
fi
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-${DB_PASS}}"

# Wait for Postgres to be ready (retry up to 30s)
echo "  Waiting for Postgres readiness..."
MAX_WAIT=30
WAIT_INTERVAL=1
ELAPSED=0
POSTGRES_READY=0

while [[ $ELAPSED -lt $MAX_WAIT ]]; do
    set +e
    if [[ "${EXEC_CTX}" == "container" ]]; then
        # In container: use PHP/PDO to check connection
        php -r "try { \$pdo = new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=postgres', '${DB_USER}', '${DB_PASS}'); exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null
    elif has_docker_compose; then
        # On host: use docker compose exec
        docker compose -f infra/docker/docker-compose.yml exec -T db pg_isready -U "$POSTGRES_USER" >/dev/null 2>&1
    else
        # Direct connection attempt
        php -r "try { \$pdo = new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=postgres', '${DB_USER}', '${DB_PASS}'); exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null
    fi
    POSTGRES_READY=$?
    set -e
    
    if [[ $POSTGRES_READY -eq 0 ]]; then
        echo "  ✓ Postgres is ready"
        break
    fi
    
    sleep $WAIT_INTERVAL
    ELAPSED=$((ELAPSED + WAIT_INTERVAL))
done

if [[ $POSTGRES_READY -ne 0 ]]; then
    echo "  ✗ Postgres not ready after ${MAX_WAIT}s"
    exit 1
fi

# Ensure database exists (idempotent)
echo "  Ensuring database $DB_NAME exists..."
DB_CREATED=0
set +e

if has_docker_compose && [[ "${EXEC_CTX}" != "container" ]]; then
    # On host: use docker compose exec with psql
    # Use clean SQL without backslash escaping
    DB_EXISTS_OUTPUT="$(docker compose -f infra/docker/docker-compose.yml exec -T db psql -U "$POSTGRES_USER" -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}';" 2>&1)"
    if echo "$DB_EXISTS_OUTPUT" | grep -q "1"; then
        echo "  ✓ Database $DB_NAME already exists"
        DB_CREATED=0
    else
        echo "  Creating database $DB_NAME..."
        docker compose -f infra/docker/docker-compose.yml exec -T db psql -U "$POSTGRES_USER" -d postgres -c "CREATE DATABASE ${DB_NAME};" >/dev/null 2>&1
        if [[ $? -eq 0 ]]; then
            echo "  ✓ Database $DB_NAME created"
            DB_CREATED=0
        else
            # May already exist (race condition), check again
            DB_EXISTS_OUTPUT="$(docker compose -f infra/docker/docker-compose.yml exec -T db psql -U "$POSTGRES_USER" -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}';" 2>&1)"
            if echo "$DB_EXISTS_OUTPUT" | grep -q "1"; then
                echo "  ✓ Database $DB_NAME exists"
                DB_CREATED=0
            else
                echo "  ✗ Failed to create database $DB_NAME"
                DB_CREATED=1
            fi
        fi
    fi
else
    # In container or no docker compose: use PHP/PDO
    # Use clean SQL without backslash escaping
    DB_CHECK_OUTPUT="$(php artisan tinker --execute="
    try {
        \$conn = new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=postgres', '${DB_USER}', '${DB_PASS}');
        \$stmt = \$conn->query(\"SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'\");
        \$exists = \$stmt->fetch() !== false;
        if (!\$exists) {
            \$conn->exec(\"CREATE DATABASE ${DB_NAME}\");
            echo 'created';
        } else {
            echo 'exists';
        }
    } catch (Exception \$e) {
        echo 'error: ' . \$e->getMessage();
        exit(1);
    }
    " 2>&1)"
    DB_CHECK_EXIT=$?
    
    if [[ $DB_CHECK_EXIT -eq 0 ]]; then
        if echo "$DB_CHECK_OUTPUT" | grep -q "created"; then
            echo "  ✓ Database $DB_NAME created"
            DB_CREATED=0
        elif echo "$DB_CHECK_OUTPUT" | grep -q "exists"; then
            echo "  ✓ Database $DB_NAME already exists"
            DB_CREATED=0
        else
            echo "  ⚠ Database check output: $DB_CHECK_OUTPUT"
            DB_CREATED=1
        fi
    else
        echo "  ⚠ Could not verify/create database: $DB_CHECK_OUTPUT"
        DB_CREATED=1
    fi
fi
set -e

if [[ $DB_CREATED -ne 0 ]]; then
    echo "  ✗ Failed to ensure database $DB_NAME exists"
    exit 1
fi
echo

# Pre-flight: Run nfts migrations
echo "Pre-flight: Running nfts migrations..."
echo
set +e
NFTS_MIGRATE_OUTPUT="$(php artisan migrate -n --database=nfts --path=database/migrations/nfts 2>&1)"
NFTS_MIGRATE_EXIT=$?
set -e
if [[ $NFTS_MIGRATE_EXIT -ne 0 ]]; then
    echo "✗ NFTs migration failed:"
    echo "$NFTS_MIGRATE_OUTPUT" | head -20
    exit 1
fi
echo "    ✓ NFTs migrations complete"
echo

# Pre-flight: Verify NFT routes exist
echo "Pre-flight: Verifying NFT routes are registered..."
set +e
ROUTE_CHECK="$(php artisan route:list --path=api/v1/nfts/mint 2>&1)"
ROUTE_CHECK_EXIT=$?
set -e
if [[ $ROUTE_CHECK_EXIT -ne 0 ]] || ! echo "$ROUTE_CHECK" | grep -q "nfts/mint"; then
    echo "✗ NFT routes are not registered"
    echo "  Expected route: POST api/v1/nfts/mint"
    echo "  Route list output:"
    echo "$ROUTE_CHECK" | head -10
    echo ""
    echo "  ERROR: NFT routes must be registered in routes/api.php"
    echo "  Check that NftController is imported and routes are defined."
    exit 1
fi
echo "    ✓ NFT routes registered"
echo

# Pre-flight: Verify Sanctum core connection
echo "Pre-flight: Verifying Sanctum core connection..."
set +e
SANCTUM_VERIFY_OUTPUT="$(php artisan imdc:verify-sanctum-core 2>&1)"
SANCTUM_VERIFY_EXIT=$?
set -e
if [[ $SANCTUM_VERIFY_EXIT -ne 0 ]]; then
    echo "✗ Sanctum core connection verification failed:"
    echo "$SANCTUM_VERIFY_OUTPUT" | head -20
    exit 1
fi
echo "$SANCTUM_VERIFY_OUTPUT" | grep -E "✓|✗" || true
echo

# Test 1: Mint authentication token (use admin user)
echo "Test 1: Minting authentication token for admin user..."
set +e
TOKEN="$(php artisan imdc:mint-debug-token --email=admin@imdc.local --database=core 2>/dev/null | tail -n 1 | tr -d "\r\n")"
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
    # Try .data.id first (handles both string and numeric IDs)
    if [[ "$path" == ".data.id" ]] || [[ "$path" == ".data.id // .id" ]]; then
        # Try .data.id pattern: "data":{"id":value (string or number)
        # Match: "data":{..."id":"value" or "data":{..."id":123
        local value="$(echo "$json" | grep -oE '"data"[[:space:]]*:[[:space:]]*\{[^}]*"id"[[:space:]]*:[[:space:]]*("([^"]+)"|[0-9]+)' | sed -nE 's/.*"id"[[:space:]]*:[[:space:]]*("([^"]+)"|([0-9]+)).*/\2\3/p' | head -1)"
        if [[ -n "$value" ]]; then
            echo "$value"
            return
        fi
        # Fallback to top-level .id (string or number)
        value="$(echo "$json" | grep -oE '"id"[[:space:]]*:[[:space:]]*("([^"]+)"|[0-9]+)' | sed -nE 's/"id"[[:space:]]*:[[:space:]]*("([^"]+)"|([0-9]+))/\2\3/p' | head -1)"
        if [[ -n "$value" ]]; then
            echo "$value"
            return
        fi
    fi
    
    # Generic fallback for .id (string or number)
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

# Extract user ID: try .data.id first, then .id as fallback
USER_ID="$(extract_json_value "$USER_BODY" ".data.id // .id")"

if [[ -z "$USER_ID" ]]; then
    echo "✗ Could not extract user ID from response"
    echo "Full JSON response:"
    echo "$USER_BODY"
    exit 1
fi

echo "✓ Current user ID: ${USER_ID}"
echo

# Create second user for transfer test
echo "Test 3: Creating second user for transfer test..."
set +e
SECOND_USER_ID="$(php artisan tinker --execute="
try {
    \$user = \App\Models\User::firstOrCreate(
        ['email' => 'nft-test-user-' . time() . '@test.local'],
        ['name' => 'NFT Test User', 'password' => bcrypt('test123')]
    );
    echo \$user->id;
} catch (Exception \$e) {
    echo 'failed: ' . \$e->getMessage();
}
" 2>/dev/null | tail -1)"
set -e

if [[ -z "$SECOND_USER_ID" ]] || [[ "$SECOND_USER_ID" == *"failed"* ]]; then
    echo "✗ Could not create second user: ${SECOND_USER_ID}"
    exit 1
fi

echo "✓ Second user created: ${SECOND_USER_ID}"
echo

# Test 4: Mint NFT token
echo "Test 4: Minting NFT token..."
CONTRACT="test-contract-$(date +%s)"
TOKEN_ID="token-$(date +%s)"
MINT_PAYLOAD="{\"contract\":\"${CONTRACT}\",\"token_id\":\"${TOKEN_ID}\",\"owner_user_id\":${USER_ID},\"metadata_uri\":\"ipfs://test\"}"

set +e
MINT_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/nfts/mint" "$TOKEN" "POST" "${MINT_PAYLOAD}")"
MINT_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "${MINT_CODE}" != "201" ]]; then
    echo "✗ NFT mint failed (HTTP ${MINT_CODE})"
    echo "$MINT_BODY" | head -20
    exit 1
fi

TOKEN_UUID="$(echo "$MINT_BODY" | grep -o '"id":"[^"]*"' | head -1 | sed 's/"id":"\([^"]*\)"/\1/' || echo '')"
if [[ -z "$TOKEN_UUID" ]]; then
    echo "✗ Could not extract token UUID from mint response"
    exit 1
fi

echo "✓ NFT token minted: ${TOKEN_UUID}"
echo

# Test 5: Transfer NFT token
echo "Test 5: Transferring NFT token..."
IDEMPOTENCY_KEY="transfer-$(date +%s)-$$"
TRANSFER_PAYLOAD="{\"token_uuid\":\"${TOKEN_UUID}\",\"to_user_id\":\"${SECOND_USER_ID}\"}"

set +e
TRANSFER_CODE1="$(curl_http_code "${API_BASE_URL}/api/v1/nfts/transfer" "$TOKEN" "POST" "${TRANSFER_PAYLOAD}" "${IDEMPOTENCY_KEY}")"
TRANSFER_BODY1="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "${TRANSFER_CODE1}" != "200" ]] && [[ "${TRANSFER_CODE1}" != "201" ]]; then
    echo "✗ NFT transfer failed (HTTP ${TRANSFER_CODE1})"
    echo "$TRANSFER_BODY1" | head -20
    exit 1
fi

TRANSFER_ID1="$(echo "$TRANSFER_BODY1" | grep -o '"id":"[^"]*"' | head -1 | sed 's/"id":"\([^"]*\)"/\1/' || echo '')"
NEW_OWNER1="$(echo "$TRANSFER_BODY1" | grep -o '"owner_user_id":"[^"]*"' | head -1 | sed 's/"owner_user_id":"\([^"]*\)"/\1/' || echo '')"

if [[ -z "$TRANSFER_ID1" ]] || [[ -z "$NEW_OWNER1" ]]; then
    echo "✗ Could not extract transfer ID or new owner from response"
    exit 1
fi

echo "✓ NFT transferred: transfer_id=${TRANSFER_ID1}, new_owner=${NEW_OWNER1}"
echo

# Test 6: Idempotency test - repeat same transfer
echo "Test 6: Testing idempotency (repeat same transfer with same Idempotency-Key)..."
set +e
TRANSFER_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/nfts/transfer" "$TOKEN" "POST" "${TRANSFER_PAYLOAD}" "${IDEMPOTENCY_KEY}")"
TRANSFER_BODY2="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "${TRANSFER_CODE2}" != "200" ]]; then
    echo "✗ Idempotency test failed: expected HTTP 200, got ${TRANSFER_CODE2}"
    exit 1
fi

TRANSFER_ID2="$(echo "$TRANSFER_BODY2" | grep -o '"id":"[^"]*"' | head -1 | sed 's/"id":"\([^"]*\)"/\1/' || echo '')"
NEW_OWNER2="$(echo "$TRANSFER_BODY2" | grep -o '"owner_user_id":"[^"]*"' | head -1 | sed 's/"owner_user_id":"\([^"]*\)"/\1/' || echo '')"

if [[ "$TRANSFER_ID1" != "$TRANSFER_ID2" ]]; then
    echo "✗ Idempotency FAILED: different transfer IDs (${TRANSFER_ID1} vs ${TRANSFER_ID2})"
    echo "  Expected: same transfer ID for same idempotency_key"
    exit 1
fi

if [[ "$NEW_OWNER1" != "$NEW_OWNER2" ]]; then
    echo "✗ Idempotency FAILED: different owners (${NEW_OWNER1} vs ${NEW_OWNER2})"
    exit 1
fi

echo "✓ Idempotency works: same transfer ID (${TRANSFER_ID1}) and owner (${NEW_OWNER1})"
echo

# Test 7: Verify WORM log chain
echo "Test 7: Verifying WORM log chain..."
set +e
WORM_LOGS_COUNT="$(php artisan tinker --execute="
try {
    \$count = \App\Nfts\Models\WormLog::count();
    echo \$count;
} catch (Exception \$e) {
    echo 'failed: ' . \$e->getMessage();
}
" 2>/dev/null | tail -1)"
set -e

if [[ -z "$WORM_LOGS_COUNT" ]] || [[ "$WORM_LOGS_COUNT" == *"failed"* ]]; then
    echo "✗ Could not count WORM logs: ${WORM_LOGS_COUNT}"
    exit 1
fi

if [[ "$WORM_LOGS_COUNT" -lt 2 ]]; then
    echo "✗ WORM log count too low: expected at least 2, got ${WORM_LOGS_COUNT}"
    exit 1
fi

echo "✓ WORM logs found: ${WORM_LOGS_COUNT}"

# Verify hash chain integrity
set +e
WORM_VERIFY="$(php artisan tinker --execute="
try {
    \$service = new \App\Services\Worm\WormLogService();
    \$result = \$service->verifyChain();
    if (\$result['valid']) {
        echo 'valid';
    } else {
        echo 'invalid: ' . implode(', ', \$result['errors']);
    }
} catch (Exception \$e) {
    echo 'failed: ' . \$e->getMessage();
}
" 2>/dev/null | tail -1)"
set -e

if [[ "$WORM_VERIFY" != "valid" ]]; then
    echo "✗ WORM chain verification failed: ${WORM_VERIFY}"
    exit 1
fi

echo "✓ WORM chain integrity verified"
echo

echo "=== NFT Guardrail PASSED ==="
exit 0
