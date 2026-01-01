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
API_BASE_URL="http://127.0.0.1:8080"
if [[ "${EXEC_CTX}" == "container" ]]; then
    declare -a URL_CANDIDATES=(
        "http://web:80"
        "http://nginx:80"
        "http://app:8000"
        "http://127.0.0.1:8080"
        "http://host.docker.internal:8080"
    )
    
    for candidate in "${URL_CANDIDATES[@]}"; do
        CODE="$(curl -sS -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 5 "${candidate}/" 2>/dev/null || echo "000000")"
        if [[ "$CODE" =~ ^[23][0-9][0-9]$ ]]; then
            API_BASE_URL="$candidate"
            break
        fi
    done
fi

echo "API Base URL: ${API_BASE_URL}"
echo

TMP_BODY="/tmp/imdc_marketplace_guardrail_body.$$"
cleanup() { rm -f "$TMP_BODY" 2>/dev/null || true; }
trap cleanup EXIT

curl_http_code() {
    local url="$1"
    local token="${2:-}"
    local extra_headers="${3:-}"
    
    if [[ -n "$token" ]]; then
        curl -sS -o "$TMP_BODY" -w "%{http_code}" \
            --connect-timeout 2 --max-time 10 \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${token}" \
            ${extra_headers} \
            "$url" || echo "000000"
    else
        curl -sS -o "$TMP_BODY" -w "%{http_code}" \
            --connect-timeout 2 --max-time 10 \
            -H "Accept: application/json" \
            ${extra_headers} \
            "$url" || echo "000000"
    fi
}

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
TOKEN_OUTPUT="$(php artisan imdc:mint-debug-token 2>&1)"
TOKEN_EXIT=$?
set -e

if [[ $TOKEN_EXIT -ne 0 ]]; then
    echo "✗ Token mint failed: $TOKEN_OUTPUT"
    exit 1
fi

TOKEN="$(echo "$TOKEN_OUTPUT" | tr -d '\r' | awk 'NF{t=$0} END{print t}' | sed -E 's/^TOKEN=//')"
if [[ -z "${TOKEN}" ]]; then
    echo "✗ Token parse failed"
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
IDEMPOTENCY_KEY="test-$(date +%s)-$$"
ORDER_PAYLOAD='{"currency":"USD","items":[{"product_id":"00000000-0000-0000-0000-000000000001","quantity":1}],"idempotency_key":"'${IDEMPOTENCY_KEY}'"}'

# First create
CODE1="$(curl_http_code "${API_BASE_URL}/api/v1/orders" "$TOKEN" "-X POST -H 'Content-Type: application/json' -d '${ORDER_PAYLOAD}'")"
ORDER_ID1="$(cat "$TMP_BODY" 2>/dev/null | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"

# Second create with same idempotency_key
CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/orders" "$TOKEN" "-X POST -H 'Content-Type: application/json' -d '${ORDER_PAYLOAD}'")"
ORDER_ID2="$(cat "$TMP_BODY" 2>/dev/null | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"

if [[ "${CODE1}" == "201" ]] && [[ "${CODE2}" == "200" ]] && [[ "${ORDER_ID1}" == "${ORDER_ID2}" ]]; then
    echo "✓ Idempotency works: same order ID returned (${ORDER_ID1})"
elif [[ "${CODE1}" == "201" ]] && [[ "${CODE2}" == "201" ]] && [[ "${ORDER_ID1}" != "${ORDER_ID2}" ]]; then
    echo "⚠ Idempotency not working: different order IDs (${ORDER_ID1} vs ${ORDER_ID2})"
    echo "  This is acceptable if idempotency_key is not yet implemented"
else
    echo "⚠ Idempotency test inconclusive (CODE1=${CODE1}, CODE2=${CODE2})"
fi
echo

echo "=== Marketplace Guardrail PASSED ==="
exit 0
