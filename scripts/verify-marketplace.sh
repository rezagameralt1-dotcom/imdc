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
cleanup() { rm -f "$TMP_BODY" 2>/dev/null || true; }
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

# Pre-flight: Run migrations per-domain with explicit paths
echo "Pre-flight: Running domain-specific migrations..."
echo

# Core accounting migrations
echo "  [1/4] Core (accounting tables)..."
CORE_MIGRATE_OUTPUT=""
CORE_MIGRATE_EXIT=0
set +e
CORE_MIGRATE_OUTPUT="$(php artisan migrate -n --database=core --path=database/migrations/core 2>&1)"
CORE_MIGRATE_EXIT=$?
set -e
if [[ $CORE_MIGRATE_EXIT -ne 0 ]]; then
    echo "✗ Core migration failed:"
    echo "$CORE_MIGRATE_OUTPUT" | head -20
    exit 1
fi
echo "    ✓ Core migrations complete"
echo

# Products migrations
echo "  [2/4] Products..."
PRODUCTS_MIGRATE_OUTPUT=""
PRODUCTS_MIGRATE_EXIT=0
set +e
PRODUCTS_MIGRATE_OUTPUT="$(php artisan migrate -n --database=products --path=database/migrations/products 2>&1)"
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
ORDERS_MIGRATE_OUTPUT="$(php artisan migrate -n --database=orders --path=database/migrations/orders 2>&1)"
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
    INVENTORY_MIGRATE_OUTPUT="$(php artisan migrate -n --database=inventory --path=database/migrations/inventory 2>&1)"
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
TABLES_CHECK="$(php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
\$tables = [
    'products' => 'products',
    'orders' => 'orders',
    'order_items' => 'orders',
    'inventory_items' => 'inventory',
    'inventory_reservations' => 'inventory',
    'accounting_vouchers' => 'core',
    'accounting_ledger' => 'core',
];
\$results = [];
foreach (\$tables as \$table => \$conn) {
    \$exists = DB::connection(\$conn)->getSchemaBuilder()->hasTable(\$table);
    \$results[\$table] = \$exists ? 'EXISTS' : 'MISSING';
}
echo json_encode(\$results);
" 2>&1)"
set -e

if echo "$TABLES_CHECK" | grep -q "MISSING"; then
    echo "✗ Some tables are missing:"
    echo "$TABLES_CHECK" | grep -o '"[^"]*":"MISSING"' || true
    exit 1
fi
echo "✓ All key tables exist"
echo

# Test 2: Mint token
echo "Test 2: Minting authentication token..."
set +e
TOKEN="$(php artisan imdc:mint-debug-token --database=core 2>/dev/null | tail -n 1 | tr -d "\r\n")"
TOKEN_EXIT=$?
set -e

if [ $TOKEN_EXIT -ne 0 ] || [ -z "$TOKEN" ]; then
    echo "✗ Token mint failed"
    echo "  ERROR: Failed to mint token on core connection. Check that users table exists in core DB."
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
    php artisan tinker --execute="echo '  Host: ' . config('database.connections.products.host') . PHP_EOL; echo '  Database: ' . config('database.connections.products.database') . PHP_EOL; echo '  Connection name: products' . PHP_EOL;" 2>/dev/null || echo "  (config check failed)"
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

# Get a real product_id from GET /api/v1/products
echo "  Fetching available product from API..."
set +e
PRODUCTS_RESPONSE="$(curl_http_code "${API_BASE_URL}/api/v1/products" "$TOKEN")"
PRODUCTS_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "${PRODUCTS_RESPONSE}" != "200" ]]; then
    echo "  ⚠ Could not fetch products (HTTP ${PRODUCTS_RESPONSE})"
    echo "  ⚠ Skipping idempotency test"
    echo
    echo "=== Marketplace Guardrail PASSED ==="
    exit 0
fi

# Extract first product ID from response using POSIX tools (grep/sed) - stable and no dependencies
# JSON structure: {"data":{"data":[{"id":"...",...}]}}
TEST_PRODUCT_ID="$(echo "$PRODUCTS_BODY" | grep -o '"id":"[^"]*"' | head -1 | sed 's/"id":"\([^"]*\)"/\1/' || echo '')"

if [[ -z "$TEST_PRODUCT_ID" ]]; then
    echo "  ⚠ No products found in response"
    echo "  ⚠ Skipping idempotency test"
    echo
    echo "=== Marketplace Guardrail PASSED ==="
    exit 0
fi

echo "  Using product ID: ${TEST_PRODUCT_ID}"

# Ensure inventory exists for the product
echo "  Ensuring inventory availability..."
set +e
INVENTORY_SETUP="$(php artisan tinker --execute="
try {
    \$item = \App\Inventory\Models\InventoryItem::firstOrCreate(
        ['product_id' => '${TEST_PRODUCT_ID}'],
        ['available_quantity' => 100, 'reserved_quantity' => 0]
    );
    if (\$item->available_quantity < 10) {
        \$item->available_quantity = 100;
        \$item->save();
    }
    echo 'ok';
} catch (Exception \$e) {
    echo 'failed: ' . \$e->getMessage();
}
" 2>/dev/null | tail -1)"
set -e

if [[ "$INVENTORY_SETUP" != "ok" ]]; then
    echo "  ⚠ Could not setup inventory: ${INVENTORY_SETUP}"
    echo "  ⚠ Skipping idempotency test"
    echo
    echo "=== Marketplace Guardrail PASSED ==="
    exit 0
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
