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
    echo "=== Marketplace Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-marketplace.sh --in-container'
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== Marketplace Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Determine API base URL
# Priority: 1) IMDC_API_BASE_URL env var (highest), 2) Container context detection, 3) Host default
if [[ -n "${IMDC_API_BASE_URL:-}" ]]; then
    API_BASE_URL="${IMDC_API_BASE_URL}"
elif [[ "${EXEC_CTX}" == "container" ]]; then
    # Inside container: try service names and host gateway URLs
    # Default to web service (docker compose service name) for infra/docker profile
    declare -a URL_CANDIDATES=(
        "http://web:80"
        "http://nginx:80"
        "http://app:8000"
    )
    
    # Try container name as fallback (docker compose creates containers with project prefix)
    if command -v getent >/dev/null 2>&1; then
        WEB_CONTAINER_IP="$(getent hosts docker-web-1 2>/dev/null | awk '{print $1}' | head -1)"
        if [[ -n "$WEB_CONTAINER_IP" ]]; then
            URL_CANDIDATES+=("http://${WEB_CONTAINER_IP}:80")
        fi
    fi
    
    # Add host gateway options (for accessing host's port 8080)
    URL_CANDIDATES+=(
        "http://host.docker.internal:8080"
        "http://172.17.0.1:8080"
        "http://gateway.docker.internal:8080"
        "http://127.0.0.1:8080"
    )
    
    FOUND_URL=""
    for candidate in "${URL_CANDIDATES[@]}"; do
        CODE="$(curl -sS -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 5 "${candidate}/" 2>/dev/null || echo "000000")"
        if [[ "$CODE" =~ ^[23][0-9][0-9]$ ]]; then
            FOUND_URL="$candidate"
            break
        fi
    done
    
    # Use found URL if available, otherwise default to web:80 (will fail later with clearer error)
    if [[ -n "$FOUND_URL" ]]; then
        API_BASE_URL="$FOUND_URL"
    else
        API_BASE_URL="http://web:80"
    fi
else
    # Host context: default to localhost
    API_BASE_URL="http://127.0.0.1:8080"
fi

echo "API Base URL: ${API_BASE_URL}"
echo

TMP_BODY="/tmp/imdc_marketplace_guardrail_body.$$"
GUARDRAIL_PRODUCT_ID=""
GUARDRAIL_RUN_ID=""

cleanup() {
    rm -f "$TMP_BODY" 2>/dev/null || true
    # Cleanup seeded guardrail product if it exists
    if [[ -n "$GUARDRAIL_PRODUCT_ID" ]]; then
        CLEANUP_SCRIPT="$ROOT_DIR/scripts/_guardrail/cleanup_guardrail_product.php"
        if [[ -f "$CLEANUP_SCRIPT" ]]; then
            echo "  Cleaning up guardrail product: ${GUARDRAIL_PRODUCT_ID}"
            export IMDC_GR_RUN_ID="$GUARDRAIL_RUN_ID"
            php "$CLEANUP_SCRIPT" "$GUARDRAIL_PRODUCT_ID" "$GUARDRAIL_RUN_ID" 2>/dev/null || true
        fi
    fi
}
trap cleanup EXIT

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
        -H "Accept: web/json"
    )
    
    if [[ -n "$token" ]]; then
        curl_args+=(-H "Authorization: Bearer ${token}")
    fi
    
    if [[ "$method" == "POST" ]] || [[ "$method" == "PUT" ]] || [[ "$method" == "PATCH" ]]; then
        curl_args+=(-X "$method" -H "Content-Type: web/json")
        if [[ -n "$data" ]]; then
            curl_args+=(-d "$data")
        fi
    fi
    
    curl "${curl_args[@]}" "$url" || echo "000000"
}

# Pre-flight: Check and reset inconsistent databases
echo "Pre-flight: Checking database consistency..."
echo

# Get POSTGRES_USER from container environment or use default
if has_docker_compose && [[ "${EXEC_CTX}" != "container" ]]; then
    POSTGRES_USER="$(docker compose -f infra/docker/docker-compose.yml exec -T db sh -c 'echo "$POSTGRES_USER"' 2>/dev/null | tr -d '\r\n' || echo "imdc")"
    POSTGRES_USER="${POSTGRES_USER:-imdc}"
else
    POSTGRES_USER="${DB_USERNAME:-imdc}"
fi

# Function: Check and reset database if inconsistent
# Args: domain_name, db_name, known_tables (space-separated)
check_and_reset_db() {
    local domain="$1"
    local db_name="$2"
    local known_tables="$3"
    
    echo "  Checking ${domain} database (${db_name})..."
    
    # Check if database exists
    set +e
    if has_docker_compose && [[ "${EXEC_CTX}" != "container" ]]; then
        DB_EXISTS="$(docker compose -f infra/docker/docker-compose.yml exec -T db psql -U "$POSTGRES_USER" -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='${db_name}';" 2>&1 | grep -q "1" && echo "yes" || echo "no")"
    else
        # In container: use PHP helper
        DB_EXISTS="$(php "$ROOT_DIR/scripts/_guardrail/check_db_exists.php" "${domain}" "${db_name}" 2>/dev/null | tail -1)"
    fi
    set -e
    
    if [[ "$DB_EXISTS" != "yes" ]]; then
        echo "    ✓ Database ${db_name} does not exist (will be created by migrations)"
        return 0
    fi
    
    # Check if migrations table exists
    set +e
    if has_docker_compose && [[ "${EXEC_CTX}" != "container" ]]; then
        MIGRATIONS_EXISTS="$(docker compose -f infra/docker/docker-compose.yml exec -T db psql -U "$POSTGRES_USER" -d "${db_name}" -tc "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='migrations';" 2>&1 | grep -q "1" && echo "yes" || echo "no")"
    else
        MIGRATIONS_EXISTS="$(php "$ROOT_DIR/scripts/_guardrail/check_table_exists.php" "${domain}" "migrations" 2>/dev/null | tail -1)"
    fi
    set -e
    
    # Check if any known domain tables exist
    local domain_tables_exist="no"
    for table in $known_tables; do
        set +e
        if has_docker_compose && [[ "${EXEC_CTX}" != "container" ]]; then
            TABLE_EXISTS="$(docker compose -f infra/docker/docker-compose.yml exec -T db psql -U "$POSTGRES_USER" -d "${db_name}" -tc "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='${table}';" 2>&1 | grep -q "1" && echo "yes" || echo "no")"
        else
            TABLE_EXISTS="$(php "$ROOT_DIR/scripts/_guardrail/check_table_exists.php" "${domain}" "${table}" 2>/dev/null | tail -1)"
        fi
        set -e
        
        if [[ "$TABLE_EXISTS" == "yes" ]]; then
            domain_tables_exist="yes"
            break
        fi
    done
    
    # If migrations table doesn't exist BUT domain tables exist => inconsistent
    if [[ "$MIGRATIONS_EXISTS" != "yes" ]] && [[ "$domain_tables_exist" == "yes" ]]; then
        echo "    ⚠ Inconsistent state detected: migrations table missing but domain tables exist"
        echo "    Resetting database ${db_name}..."
        
        set +e
        if has_docker_compose && [[ "${EXEC_CTX}" != "container" ]]; then
            # Drop database with FORCE (Postgres 13+)
            docker compose -f infra/docker/docker-compose.yml exec -T db psql -U "$POSTGRES_USER" -d postgres -c "DROP DATABASE IF EXISTS ${db_name} WITH (FORCE);" >/dev/null 2>&1
            # Recreate database
            docker compose -f infra/docker/docker-compose.yml exec -T db psql -U "$POSTGRES_USER" -d postgres -c "CREATE DATABASE ${db_name};" >/dev/null 2>&1
            RESET_EXIT=$?
        else
            # In container: use PHP helper
            RESET_OUTPUT="$(php "$ROOT_DIR/scripts/_guardrail/reset_database.php" "${domain}" "${db_name}" 2>/dev/null | tail -1)"
            if [[ "$RESET_OUTPUT" == "ok" ]]; then
                RESET_EXIT=0
            else
                RESET_EXIT=1
            fi
        fi
        set -e
        
        if [[ $RESET_EXIT -eq 0 ]]; then
            echo "    ✓ Database ${db_name} reset successfully"
        else
            echo "    ✗ Failed to reset database ${db_name}"
            exit 1
        fi
    else
        if [[ "$MIGRATIONS_EXISTS" == "yes" ]]; then
            echo "    ✓ Migrations table exists (consistent state)"
        else
            echo "    ✓ No domain tables found (clean state)"
        fi
    fi
}

# Check each domain database
check_and_reset_db "core" "imdc_core" "users roles permissions personal_access_tokens accounting_vouchers"
check_and_reset_db "products" "imdc_products" "categories products"
check_and_reset_db "orders" "imdc_orders" "orders order_items"
check_and_reset_db "inventory" "imdc_inventory" "inventories stock_movements"
echo

# Pre-flight: Run migrations per-domain with explicit paths
echo "Pre-flight: Running domain-specific migrations..."
echo

# Core accounting migrations
echo "  [1/4] Core (users, RBAC, accounting tables)..."
CORE_MIGRATE_OUTPUT=""
CORE_MIGRATE_EXIT=0
set +e
CORE_MIGRATE_OUTPUT="$(php artisan migrate --force --database=core --path=database/migrations/core 2>&1)"
CORE_MIGRATE_EXIT=$?
set -e
if [[ $CORE_MIGRATE_EXIT -ne 0 ]]; then
    echo "✗ Core migration failed:"
    echo "$CORE_MIGRATE_OUTPUT" | head -20
    exit 1
fi
echo "    ✓ Core migrations complete"

# Seed admin user after core migrations
echo "  Seeding admin user..."
set +e
SEED_OUTPUT="$(php artisan db:seed --class=AdminUserSeeder --database=core --force 2>&1)"
SEED_EXIT=$?
set -e
if [[ $SEED_EXIT -ne 0 ]]; then
    echo "  ⚠ Admin user seeding failed (may already exist):"
    echo "$SEED_OUTPUT" | head -10
else
    echo "    ✓ Admin user seeded"
fi
echo

# Verify Sanctum core connection
echo "  Verifying Sanctum core connection..."
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

# Products migrations
echo "  [2/4] Products..."
PRODUCTS_MIGRATE_OUTPUT=""
PRODUCTS_MIGRATE_EXIT=0
set +e
PRODUCTS_MIGRATE_OUTPUT="$(php artisan migrate --force --database=products --path=database/migrations/products 2>&1)"
PRODUCTS_MIGRATE_EXIT=$?
set -e
if [[ $PRODUCTS_MIGRATE_EXIT -ne 0 ]]; then
    echo "✗ Products migration failed:"
    echo "$PRODUCTS_MIGRATE_OUTPUT" | head -20
    exit 1
fi
echo "    ✓ Products migrations complete"
echo

# Orders migrations
echo "  [3/4] Orders..."
ORDERS_MIGRATE_OUTPUT=""
ORDERS_MIGRATE_EXIT=0
set +e
ORDERS_MIGRATE_OUTPUT="$(php artisan migrate --force --database=orders --path=database/migrations/orders 2>&1)"
ORDERS_MIGRATE_EXIT=$?
set -e
if [[ $ORDERS_MIGRATE_EXIT -ne 0 ]]; then
    echo "✗ Orders migration failed:"
    echo "$ORDERS_MIGRATE_OUTPUT" | head -20
    exit 1
fi
echo "    ✓ Orders migrations complete"
echo

# Inventory migrations (conditional)
echo "  [4/4] Inventory..."
if [[ -d "database/migrations/inventory" ]]; then
    INVENTORY_MIGRATE_OUTPUT=""
    INVENTORY_MIGRATE_EXIT=0
    set +e
    INVENTORY_MIGRATE_OUTPUT="$(php artisan migrate --force --database=inventory --path=database/migrations/inventory 2>&1)"
    INVENTORY_MIGRATE_EXIT=$?
    set -e
    if [[ $INVENTORY_MIGRATE_EXIT -ne 0 ]]; then
        echo "✗ Inventory migration failed:"
        echo "$INVENTORY_MIGRATE_OUTPUT" | head -20
        exit 1
    fi
    echo "    ✓ Inventory migrations complete"
else
    echo "    WARN: database/migrations/inventory not found; skipping inventory migrate"
fi
echo

# Test 1: Check key tables exist
echo "Test 1: Checking key tables exist..."
set +e
TABLES_CHECK="$(php "$ROOT_DIR/scripts/_guardrail/check_tables_exist.php" 2>&1)"
set -e

if echo "$TABLES_CHECK" | grep -q "MISSING"; then
    echo "✗ Some tables are missing:"
    echo "$TABLES_CHECK" | grep -o '"[^"]*":"MISSING"' || true
    exit 1
fi
echo "✓ All key tables exist"
echo

# Test 2: Mint token (use Admin role user via Spatie, fallback to first user)
echo "Test 2: Minting authentication token..."
set +e
TOKEN_OUTPUT="$(php "$ROOT_DIR/scripts/_guardrail/mint_token_admin.php" 2>&1)"
TOKEN_EXIT=$?
set -e

if [ $TOKEN_EXIT -ne 0 ]; then
    echo "✗ Token mint failed"
    echo "$TOKEN_OUTPUT" | grep -E "ERROR|Exception" | head -5 || echo "  ERROR: Failed to mint token on core connection."
    exit 1
fi

TOKEN="$(echo "$TOKEN_OUTPUT" | tail -1 | tr -d "\r\n")"
if [ -z "$TOKEN" ]; then
    echo "✗ Token mint failed: empty token"
    exit 1
fi

echo "✓ Token minted (${#TOKEN} chars)"
echo

# Test 3: Products endpoint returns 200
echo "Test 3: Checking GET /api/v1/products returns 200..."
CODE="$(curl_http_code "${API_BASE_URL}/api/v1/products" "$TOKEN")"
if [[ "${CODE}" == "200" ]]; then
    echo "✓ Products endpoint returns HTTP 200"
else
    echo "✗ Products endpoint returned HTTP ${CODE}"
    sed -n '1,50p' "$TMP_BODY" 2>/dev/null || true
    
    # Diagnostics on failure
    echo
    echo "=== Diagnostics ==="
    echo
    
    # Route/controller target
    echo "Route/Controller target for /api/v1/products:"
    php artisan route:list --path=api/v1/products 2>/dev/null | head -5 || echo "  (route:list failed)"
    echo
    
    # Current DB connection resolved for products
    echo "Products DB connection config:"
    php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo '  Host: ' . config('database.connections.products.host') . PHP_EOL; echo '  Database: ' . config('database.connections.products.database') . PHP_EOL; echo '  Connection name: products' . PHP_EOL;" 2>/dev/null || echo "  (config check failed)"
    echo
    
    # Laravel exception stacktrace (last ~200 lines)
    if [[ -f "storage/logs/laravel.log" ]]; then
        echo "Laravel exception stacktrace (last 200 lines):"
        tail -200 storage/logs/laravel.log 2>/dev/null | grep -A 30 "Exception\|Error\|SQLSTATE" | tail -100 || echo "  (no recent errors in log)"
    else
        echo "Laravel log file not found: storage/logs/laravel.log"
    fi
    echo
    
    # Web nginx error log (if accessible)
    if [[ "${EXEC_CTX}" == "container" ]] && command -v curl >/dev/null 2>&1; then
        echo "Nginx error log (if accessible):"
        # Try to get nginx error log from web container
        if docker ps --format "{{.Names}}" 2>/dev/null | grep -q "web"; then
            docker logs "$(docker ps --format "{{.Names}}" | grep web | head -1)" 2>&1 | tail -50 | grep -i "error\|emerg\|crit" || echo "  (no nginx errors found)"
        else
            echo "  (web container not found)"
        fi
    fi
    echo
    
    exit 1
fi
echo

# Test 4: Orders endpoint returns 200
echo "Test 4: Checking GET /api/v1/orders returns 200..."
CODE="$(curl_http_code "${API_BASE_URL}/api/v1/orders" "$TOKEN")"
if [[ "${CODE}" == "200" ]]; then
    echo "✓ Orders endpoint returns HTTP 200"
else
    echo "✗ Orders endpoint returned HTTP ${CODE}"
    sed -n '1,50p' "$TMP_BODY" 2>/dev/null || true
    exit 1
fi
echo

# Test 5: Idempotency test for order creation
echo "Test 5: Testing order creation idempotency..."

# Ensure guardrail product exists (deterministic - never skip)
echo "  Ensuring guardrail product exists..."
ENSURE_SCRIPT="$ROOT_DIR/scripts/_guardrail/ensure_guardrail_product.php"
if [[ ! -f "$ENSURE_SCRIPT" ]]; then
    echo "  ✗ Guardrail helper script not found: $ENSURE_SCRIPT"
    exit 1
fi

set +e
# Capture STDOUT (product ID) and STDERR (diagnostics) separately using temp files
ENSURE_STDOUT_TMP="/tmp/imdc_ensure_stdout.$$"
ENSURE_STDERR_TMP="/tmp/imdc_ensure_stderr.$$"
php "$ENSURE_SCRIPT" > "$ENSURE_STDOUT_TMP" 2> "$ENSURE_STDERR_TMP"
ENSURE_EXIT=$?
set -e

if [[ $ENSURE_EXIT -ne 0 ]]; then
    echo "  ✗ Failed to ensure guardrail product:"
    head -10 "$ENSURE_STDERR_TMP" 2>/dev/null || true
    rm -f "$ENSURE_STDOUT_TMP" "$ENSURE_STDERR_TMP" 2>/dev/null || true
    exit 1
fi

# Extract product ID from STDOUT (machine-parseable, should be only the UUID)
TEST_PRODUCT_ID="$(cat "$ENSURE_STDOUT_TMP" 2>/dev/null | tail -1 | tr -d '\r\n')"

# Extract run_id from stderr (RUN_ID: <uuid>)
GUARDRAIL_RUN_ID="$(grep -E 'RUN_ID:' "$ENSURE_STDERR_TMP" 2>/dev/null | sed -n 's/.*RUN_ID:[[:space:]]*\([^[:space:]]*\).*/\1/p' | head -1 || echo '')"

# Check if this is a newly created guardrail product (for cleanup)
if grep -q "Created product for idempotency test" "$ENSURE_STDERR_TMP" 2>/dev/null; then
    GUARDRAIL_PRODUCT_ID="$TEST_PRODUCT_ID"
    export IMDC_GR_RUN_ID="$GUARDRAIL_RUN_ID"
fi

# Cleanup temp files
rm -f "$ENSURE_STDOUT_TMP" "$ENSURE_STDERR_TMP" 2>/dev/null || true

if [[ -z "$TEST_PRODUCT_ID" ]]; then
    echo "  ✗ Failed to extract product ID from ensure output"
    exit 1
fi

echo "  ✓ Using product ID: ${TEST_PRODUCT_ID}"

# Ensure inventory exists for the product
echo "  Ensuring inventory availability..."
set +e
INVENTORY_SETUP="$(php "$ROOT_DIR/scripts/_guardrail/ensure_inventory.php" "${TEST_PRODUCT_ID}" 2>/dev/null | tail -1)"
set -e

if [[ "$INVENTORY_SETUP" != "ok" ]]; then
    echo "  ✗ Could not setup inventory: ${INVENTORY_SETUP}"
    echo "  ✗ Idempotency test cannot proceed without inventory"
    exit 1
fi

IDEMPOTENCY_KEY="test-$(date +%s)-$$"
# Ensure items array is properly formatted with required fields: product_id (UUID) and quantity (integer >= 1)
ORDER_PAYLOAD="{\"currency\":\"USD\",\"items\":[{\"product_id\":\"${TEST_PRODUCT_ID}\",\"quantity\":1}],\"idempotency_key\":\"${IDEMPOTENCY_KEY}\"}"

# First create
CODE1="$(curl_http_code "${API_BASE_URL}/api/v1/orders" "$TOKEN" "POST" "${ORDER_PAYLOAD}")"
ORDER_ID1="$(cat "$TMP_BODY" 2>/dev/null | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"

# Second create with same idempotency_key
CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/orders" "$TOKEN" "POST" "${ORDER_PAYLOAD}")"
ORDER_ID2="$(cat "$TMP_BODY" 2>/dev/null | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"

# Idempotency test: must return same order ID with same idempotency key
if [[ "${CODE1}" == "201" ]] && [[ "${CODE2}" == "200" ]] && [[ "${ORDER_ID1}" == "${ORDER_ID2}" ]] && [[ -n "${ORDER_ID1}" ]]; then
    echo "✓ Idempotency works: same order ID returned (${ORDER_ID1})"
elif [[ "${CODE1}" == "201" ]] && [[ "${CODE2}" == "201" ]] && [[ "${ORDER_ID1}" != "${ORDER_ID2}" ]]; then
    echo "✗ Idempotency FAILED: different order IDs (${ORDER_ID1} vs ${ORDER_ID2})"
    echo "  Expected: same order ID for same idempotency_key"
    exit 1
elif [[ "${CODE1}" != "201" ]] || [[ "${CODE2}" != "200" ]]; then
    echo "✗ Idempotency test FAILED: unexpected HTTP codes (CODE1=${CODE1}, CODE2=${CODE2})"
    echo "  Expected: CODE1=201 (created), CODE2=200 (existing)"
    exit 1
else
    echo "✗ Idempotency test FAILED: order IDs missing or mismatch"
    echo "  ORDER_ID1=${ORDER_ID1}, ORDER_ID2=${ORDER_ID2}"
    exit 1
fi
echo

echo "=== Marketplace Guardrail PASSED ==="
exit 0
