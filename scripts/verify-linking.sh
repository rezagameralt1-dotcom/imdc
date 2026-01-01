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
    echo "=== Linking Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-linking.sh --in-container'
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== Linking Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Check FEATURE_LINKING from .env
ENV_FILE="${ROOT_DIR}/.env"
SHOULD_SKIP=false

if [[ -f "$ENV_FILE" ]]; then
    FEATURE_LINKING_FROM_ENV="$(grep -E "^FEATURE_LINKING=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    if [[ -n "$FEATURE_LINKING_FROM_ENV" ]]; then
        FEATURE_LINKING_NORMALIZED="$(echo "$FEATURE_LINKING_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_LINKING_NORMALIZED" == "false" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "0" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "no" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "off" ]]; then
            SHOULD_SKIP=true
        fi
    fi
else
    if [[ "${FEATURE_LINKING:-false}" != "true" ]]; then
        SHOULD_SKIP=true
    fi
fi

if [[ "$SHOULD_SKIP" == "true" ]]; then
    echo "FEATURE_LINKING is not enabled (FEATURE_LINKING=${FEATURE_LINKING_FROM_ENV:-${FEATURE_LINKING:-false}})"
    echo "SKIP: FEATURE_LINKING=false"
    exit 0
fi

# Guardrail: Temporarily ensure FEATURE_LINKING=true
echo "Guardrail: Temporarily ensuring FEATURE_LINKING=true for verification..."
echo

export FEATURE_LINKING=true

ENV_BACKUP=""
TMP_BODY="/tmp/imdc_linking_guardrail_body.$$"
cleanup() {
    rm -f "$TMP_BODY" 2>/dev/null || true
    if [[ -n "$ENV_BACKUP" ]] && [[ -f "$ENV_BACKUP" ]] && [[ -n "$ENV_FILE" ]]; then
        echo "Restoring original .env file..."
        if [[ -f "$ENV_FILE" ]]; then
            mv "$ENV_BACKUP" "$ENV_FILE" 2>/dev/null || true
            php artisan config:clear 2>/dev/null || true
            php artisan cache:clear 2>/dev/null || true
            php artisan route:clear 2>/dev/null || true
        fi
    fi
}
trap cleanup EXIT

if [[ -f "$ENV_FILE" ]]; then
    TIMESTAMP="$(date +%s)"
    ENV_BACKUP="${ENV_FILE}.bak.verify-linking.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"

    if grep -qE "^FEATURE_LINKING=" "$ENV_FILE" 2>/dev/null; then
        if [[ "$(uname)" == "Darwin" ]]; then
            sed -i '' 's/^FEATURE_LINKING=.*/FEATURE_LINKING=true/' "$ENV_FILE"
        else
            sed -i 's/^FEATURE_LINKING=.*/FEATURE_LINKING=true/' "$ENV_FILE"
        fi
        echo "  ✓ Updated FEATURE_LINKING=true in .env"
    else
        echo "FEATURE_LINKING=true" >> "$ENV_FILE"
        echo "  ✓ Added FEATURE_LINKING=true to .env"
    fi
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

# Clear caches
echo "Pre-flight: Clearing caches..."
set +e
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
set -e
echo "    ✓ Caches cleared"
echo

# Test 1: Run core migrations
echo "Test 1: Running core migrations..."
set +e
php artisan migrate --database=core --path=database/migrations/core 2>&1 | tee "$TMP_BODY" || true
set -e
echo "    ✓ Migrations completed"
echo

# Test 2: Mint token
echo "Test 2: Minting authentication token..."
set +e
TOKEN_OUTPUT="$(php "$ROOT_DIR/scripts/_guardrail/mint_token_admin.php" 2>&1)"
TOKEN_EXIT=$?
set -e

if [ $TOKEN_EXIT -ne 0 ]; then
    echo "✗ Token mint failed"
    echo "$TOKEN_OUTPUT" | grep -E "ERROR|Exception" | head -5 || echo "  ERROR: Failed to mint token"
    exit 1
fi

TOKEN="$(echo "$TOKEN_OUTPUT" | tail -1 | tr -d "\r\n")"
if [ -z "$TOKEN" ]; then
    echo "✗ Token mint failed: empty token"
    exit 1
fi

echo "✓ Token minted"
echo

# Helper function for API calls
curl_http_code() {
    local url="$1"
    local token="$2"
    curl -s -w "%{http_code}" -o "$TMP_BODY" \
        -H "Authorization: Bearer ${token}" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        "$url" || echo "000"
}

curl_post() {
    local url="$1"
    local token="$2"
    local data="$3"
    curl -s -w "\n%{http_code}" -o "$TMP_BODY" \
        -X POST \
        -H "Authorization: Bearer ${token}" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "$data" \
        "$url" || echo "000"
}

# Test 3: Ensure DID exists
echo "Test 3: Ensuring DID exists..."
set +e
DID_OUTPUT="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Services\Did\DidService;
use App\Models\User;
\$user = User::on('core')->whereHas('roles', function(\$q) { \$q->where('name', 'Admin'); })->first() ?: User::on('core')->first();
if (!\$user) { fwrite(STDERR, 'ERROR: No users found\n'); exit(1); }
\$didService = new DidService();
\$profile = \$didService->getOrCreate(\$user->id);
echo \$profile->id . PHP_EOL;
" 2>&1)"
DID_EXIT=$?
set -e

if [ $DID_EXIT -ne 0 ]; then
    echo "✗ DID creation failed"
    echo "$DID_OUTPUT" | grep -E "ERROR|Exception" | head -5 || echo "  ERROR: Failed to create DID"
    exit 1
fi

DID_ID="$(echo "$DID_OUTPUT" | tail -1 | tr -d "\r\n")"
if [ -z "$DID_ID" ]; then
    echo "✗ DID creation failed: empty DID ID"
    exit 1
fi

echo "✓ DID exists: ${DID_ID}"
echo

# Test 4: Ensure Product + Inventory + Order exist
echo "Test 4: Ensuring Product + Inventory + Order exist..."
set +e
ORDER_OUTPUT="$(php "$ROOT_DIR/scripts/_guardrail/ensure_guardrail_product.php" 2>&1)"
ORDER_EXIT=$?
set -e

if [ $ORDER_EXIT -ne 0 ]; then
    echo "✗ Product/Order setup failed"
    echo "$ORDER_OUTPUT" | grep -E "ERROR|Exception" | head -5 || echo "  ERROR: Failed to setup product/order"
    exit 1
fi

PRODUCT_ID="$(echo "$ORDER_OUTPUT" | grep -E "^PRODUCT_ID=" | cut -d'=' -f2 | tr -d '\r\n')"
ORDER_ID="$(echo "$ORDER_OUTPUT" | grep -E "^ORDER_ID=" | cut -d'=' -f2 | tr -d '\r\n')"

if [ -z "$PRODUCT_ID" ] || [ -z "$ORDER_ID" ]; then
    echo "✗ Product/Order setup failed: missing IDs"
    exit 1
fi

echo "✓ Product ID: ${PRODUCT_ID}"
echo "✓ Order ID: ${ORDER_ID}"
echo

# Test 5: Ensure NFT exists
echo "Test 5: Ensuring NFT exists..."
# First ensure FEATURE_NFT is enabled for NFT mint
if [[ -f "$ENV_FILE" ]]; then
    if ! grep -qE "^FEATURE_NFT=true" "$ENV_FILE" 2>/dev/null; then
        if grep -qE "^FEATURE_NFT=" "$ENV_FILE" 2>/dev/null; then
            if [[ "$(uname)" == "Darwin" ]]; then
                sed -i '' 's/^FEATURE_NFT=.*/FEATURE_NFT=true/' "$ENV_FILE"
            else
                sed -i 's/^FEATURE_NFT=.*/FEATURE_NFT=true/' "$ENV_FILE"
            fi
        else
            echo "FEATURE_NFT=true" >> "$ENV_FILE"
        fi
        php artisan config:clear 2>/dev/null || true
    fi
fi
export FEATURE_NFT=true

set +e
NFT_DATA="{\"contract\":\"imdc-test\",\"token_id\":\"linking-test-$(date +%s)\",\"owner_user_id\":1,\"metadata_uri\":\"ipfs://test\"}"
NFT_RESPONSE="$(curl_post "${API_BASE_URL}/api/v1/nfts/mint" "$TOKEN" "$NFT_DATA")"
NFT_HTTP_CODE="$(echo "$NFT_RESPONSE" | tail -1)"
NFT_BODY="$(echo "$NFT_RESPONSE" | head -n -1)"
set -e

if [[ "$NFT_HTTP_CODE" != "201" ]] && [[ "$NFT_HTTP_CODE" != "200" ]]; then
    echo "✗ NFT mint failed: HTTP ${NFT_HTTP_CODE}"
    echo "$NFT_BODY" | head -20
    exit 1
fi

NFT_ID="$(echo "$NFT_BODY" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['id'] ?? '';")"
if [ -z "$NFT_ID" ]; then
    echo "✗ NFT mint failed: could not extract NFT ID"
    exit 1
fi

echo "✓ NFT exists: ${NFT_ID}"
echo

# Test 6: Create links
echo "Test 6: Creating links..."
set +e

# DID-Order link
DID_ORDER_DATA="{\"did_id\":\"${DID_ID}\",\"order_id\":\"${ORDER_ID}\",\"scope\":\"ownership\"}"
DID_ORDER_RESPONSE="$(curl_post "${API_BASE_URL}/api/v1/linking/did-order" "$TOKEN" "$DID_ORDER_DATA")"
DID_ORDER_CODE="$(echo "$DID_ORDER_RESPONSE" | tail -1)"
if [[ "$DID_ORDER_CODE" != "201" ]] && [[ "$DID_ORDER_CODE" != "200" ]]; then
    echo "✗ DID-Order link failed: HTTP ${DID_ORDER_CODE}"
    echo "$DID_ORDER_RESPONSE" | head -20
    exit 1
fi
echo "✓ DID-Order link created"

# DID-NFT link
DID_NFT_DATA="{\"did_id\":\"${DID_ID}\",\"nft_id\":\"${NFT_ID}\",\"role\":\"owner\"}"
DID_NFT_RESPONSE="$(curl_post "${API_BASE_URL}/api/v1/linking/did-nft" "$TOKEN" "$DID_NFT_DATA")"
DID_NFT_CODE="$(echo "$DID_NFT_RESPONSE" | tail -1)"
if [[ "$DID_NFT_CODE" != "201" ]] && [[ "$DID_NFT_CODE" != "200" ]]; then
    echo "✗ DID-NFT link failed: HTTP ${DID_NFT_CODE}"
    echo "$DID_NFT_RESPONSE" | head -20
    exit 1
fi
echo "✓ DID-NFT link created"

# Order-NFT link
ORDER_NFT_DATA="{\"order_id\":\"${ORDER_ID}\",\"nft_id\":\"${NFT_ID}\",\"purpose\":\"fulfillment\"}"
ORDER_NFT_RESPONSE="$(curl_post "${API_BASE_URL}/api/v1/linking/order-nft" "$TOKEN" "$ORDER_NFT_DATA")"
ORDER_NFT_CODE="$(echo "$ORDER_NFT_RESPONSE" | tail -1)"
if [[ "$ORDER_NFT_CODE" != "201" ]] && [[ "$ORDER_NFT_CODE" != "200" ]]; then
    echo "✗ Order-NFT link failed: HTTP ${ORDER_NFT_CODE}"
    echo "$ORDER_NFT_RESPONSE" | head -20
    exit 1
fi
echo "✓ Order-NFT link created"
echo

# Test 7: Test idempotency (re-run same links)
echo "Test 7: Testing idempotency..."
set +e

DID_ORDER_RESPONSE2="$(curl_post "${API_BASE_URL}/api/v1/linking/did-order" "$TOKEN" "$DID_ORDER_DATA")"
DID_ORDER_CODE2="$(echo "$DID_ORDER_RESPONSE2" | tail -1)"
if [[ "$DID_ORDER_CODE2" != "200" ]] && [[ "$DID_ORDER_CODE2" != "201" ]]; then
    echo "✗ DID-Order idempotency failed: HTTP ${DID_ORDER_CODE2}"
    exit 1
fi
echo "✓ DID-Order link idempotent"

DID_NFT_RESPONSE2="$(curl_post "${API_BASE_URL}/api/v1/linking/did-nft" "$TOKEN" "$DID_NFT_DATA")"
DID_NFT_CODE2="$(echo "$DID_NFT_RESPONSE2" | tail -1)"
if [[ "$DID_NFT_CODE2" != "200" ]] && [[ "$DID_NFT_CODE2" != "201" ]]; then
    echo "✗ DID-NFT idempotency failed: HTTP ${DID_NFT_CODE2}"
    exit 1
fi
echo "✓ DID-NFT link idempotent"

ORDER_NFT_RESPONSE2="$(curl_post "${API_BASE_URL}/api/v1/linking/order-nft" "$TOKEN" "$ORDER_NFT_DATA")"
ORDER_NFT_CODE2="$(echo "$ORDER_NFT_RESPONSE2" | tail -1)"
if [[ "$ORDER_NFT_CODE2" != "200" ]] && [[ "$ORDER_NFT_CODE2" != "201" ]]; then
    echo "✗ Order-NFT idempotency failed: HTTP ${ORDER_NFT_CODE2}"
    exit 1
fi
echo "✓ Order-NFT link idempotent"
echo

# Test 8: Validate in DB
echo "Test 8: Validating links in database..."
set +e
DB_VALIDATION="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\DidOrderLink;
use App\Models\DidNftLink;
use App\Models\OrderNftLink;
\$didOrderCount = DidOrderLink::where('did_id', '${DID_ID}')->where('order_id', '${ORDER_ID}')->count();
\$didNftCount = DidNftLink::where('did_id', '${DID_ID}')->where('nft_id', '${NFT_ID}')->count();
\$orderNftCount = OrderNftLink::where('order_id', '${ORDER_ID}')->where('nft_id', '${NFT_ID}')->count();
if (\$didOrderCount != 1) { echo 'FAIL: DidOrderLink count=' . \$didOrderCount . ' (expected 1)\n'; exit(1); }
if (\$didNftCount != 1) { echo 'FAIL: DidNftLink count=' . \$didNftCount . ' (expected 1)\n'; exit(1); }
if (\$orderNftCount != 1) { echo 'FAIL: OrderNftLink count=' . \$orderNftCount . ' (expected 1)\n'; exit(1); }
echo 'PASS: All links validated\n';
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

# Test 9: Test read endpoints
echo "Test 9: Testing read endpoints..."
set +e

DID_LINKS_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/linking/did/${DID_ID}" "$TOKEN")"
if [[ "$DID_LINKS_CODE" != "200" ]]; then
    echo "✗ GET /api/v1/linking/did/{didId} failed: HTTP ${DID_LINKS_CODE}"
    exit 1
fi
echo "✓ GET /api/v1/linking/did/{didId} returns 200"

ORDER_LINKS_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/linking/order/${ORDER_ID}" "$TOKEN")"
if [[ "$ORDER_LINKS_CODE" != "200" ]]; then
    echo "✗ GET /api/v1/linking/order/{orderId} failed: HTTP ${ORDER_LINKS_CODE}"
    exit 1
fi
echo "✓ GET /api/v1/linking/order/{orderId} returns 200"

NFT_LINKS_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/linking/nft/${NFT_ID}" "$TOKEN")"
if [[ "$NFT_LINKS_CODE" != "200" ]]; then
    echo "✗ GET /api/v1/linking/nft/{nftId} failed: HTTP ${NFT_LINKS_CODE}"
    exit 1
fi
echo "✓ GET /api/v1/linking/nft/{nftId} returns 200"
echo

# Cleanup (optional - guardrail products are cleaned up by verify-marketplace)
echo "✓ All linking tests PASSED"
echo
echo "=== Linking Verification Complete ==="
