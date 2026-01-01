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

# Check FEATURE_LINKING from .env file (deterministic detection)
ENV_FILE="${ROOT_DIR}/.env"
SHOULD_SKIP=false

if [[ -f "$ENV_FILE" ]]; then
    # Read FEATURE_LINKING from .env file (before any modifications)
    FEATURE_LINKING_FROM_ENV="$(grep -E "^FEATURE_LINKING=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    # Normalize: check if it's explicitly set to false (case-insensitive)
    if [[ -n "$FEATURE_LINKING_FROM_ENV" ]]; then
        FEATURE_LINKING_NORMALIZED="$(echo "$FEATURE_LINKING_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_LINKING_NORMALIZED" == "false" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "0" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "no" ]] || [[ "$FEATURE_LINKING_NORMALIZED" == "off" ]]; then
            SHOULD_SKIP=true
        fi
    fi
    # If FEATURE_LINKING is missing from .env, we'll proceed and add it (don't skip)
else
    # If .env doesn't exist, check shell env (default behavior: skip if not explicitly true)
    if [[ "${FEATURE_LINKING:-false}" != "true" ]]; then
        SHOULD_SKIP=true
    fi
fi

if [[ "$SHOULD_SKIP" == "true" ]]; then
    echo "FEATURE_LINKING is not enabled (FEATURE_LINKING=${FEATURE_LINKING_FROM_ENV:-${FEATURE_LINKING:-false}})"
    echo "SKIP: FEATURE_LINKING=false"
    exit 0
fi

# Guardrail: Temporarily ensure FEATURE_LINKING=true for the duration of the script
echo "Guardrail: Temporarily ensuring FEATURE_LINKING=true for verification..."
echo

# Export FEATURE_LINKING=true for all child processes (artisan, curl, etc.)
export FEATURE_LINKING=true

# Also export FEATURE_NFT=true for NFT minting
export FEATURE_NFT=true

# Backup and modify .env file so php-fpm workers can read FEATURE_LINKING=true
ENV_BACKUP=""

# Initialize cleanup function early (before .env modification)
TMP_BODY="/tmp/imdc_linking_guardrail_body.$$"
cleanup() {
    rm -f "$TMP_BODY" 2>/dev/null || true
    # Restore .env if it was backed up
    if [[ -n "$ENV_BACKUP" ]] && [[ -f "$ENV_BACKUP" ]] && [[ -n "$ENV_FILE" ]]; then
        echo "Restoring original .env file..."
        if [[ -f "$ENV_FILE" ]]; then
            mv "$ENV_BACKUP" "$ENV_FILE" 2>/dev/null || true
            # Clear caches after restore
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
    ENV_BACKUP="${ENV_FILE}.bak.verify-linking.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"

    # Check if FEATURE_LINKING is already enabled
    FEATURE_LINKING_CURRENT="$(grep -E "^FEATURE_LINKING=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    FEATURE_LINKING_CURRENT_NORMALIZED="$(echo "$FEATURE_LINKING_CURRENT" | tr '[:upper:]' '[:lower:]' | tr -d ' ' || echo "")"
    
    if [[ "$FEATURE_LINKING_CURRENT_NORMALIZED" == "true" ]] || [[ "$FEATURE_LINKING_CURRENT_NORMALIZED" == "1" ]] || [[ "$FEATURE_LINKING_CURRENT_NORMALIZED" == "yes" ]] || [[ "$FEATURE_LINKING_CURRENT_NORMALIZED" == "on" ]]; then
        echo "  ✓ FEATURE_LINKING already enabled in .env"
    else
        # Ensure FEATURE_LINKING=true is set in .env
        if grep -qE "^FEATURE_LINKING=" "$ENV_FILE" 2>/dev/null; then
            # Replace existing line
            if [[ "$(uname)" == "Darwin" ]]; then
                # macOS sed
                sed -i '' 's/^FEATURE_LINKING=.*/FEATURE_LINKING=true/' "$ENV_FILE"
            else
                # Linux sed
                sed -i 's/^FEATURE_LINKING=.*/FEATURE_LINKING=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_LINKING=true in .env"
        else
            # Append if not exists
            echo "FEATURE_LINKING=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_LINKING=true to .env"
        fi
    fi

    # Also ensure FEATURE_NFT=true for NFT minting
    if ! grep -qE "^FEATURE_NFT=true" "$ENV_FILE" 2>/dev/null; then
        if grep -qE "^FEATURE_NFT=" "$ENV_FILE" 2>/dev/null; then
            if [[ "$(uname)" == "Darwin" ]]; then
                sed -i '' 's/^FEATURE_NFT=.*/FEATURE_NFT=true/' "$ENV_FILE"
            else
                sed -i 's/^FEATURE_NFT=.*/FEATURE_NFT=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_NFT=true in .env"
        else
            echo "FEATURE_NFT=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_NFT=true to .env"
        fi
    else
        echo "  ✓ FEATURE_NFT already enabled in .env"
    fi
else
    echo "  ⚠ .env file not found at ${ENV_FILE}"
    echo "  Guardrail will attempt to run with exported FEATURE_LINKING=true"
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

# Pre-flight: Clear caches to ensure FEATURE_LINKING from .env is read at runtime
echo "Pre-flight: Clearing caches to ensure FEATURE_LINKING from .env is read at runtime..."
echo
set +e
php artisan optimize:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
set -e
echo "    ✓ Caches cleared (config, cache, route)"
echo

# Debug: Show effective FEATURE_LINKING value from .env
echo "Debug: Verifying FEATURE_LINKING is enabled..."
if [[ -f "$ENV_FILE" ]]; then
    FEATURE_LINKING_FROM_ENV_AFTER="$(grep -E "^FEATURE_LINKING=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "not found")"
    echo "  FEATURE_LINKING in .env: ${FEATURE_LINKING_FROM_ENV_AFTER}"
else
    echo "  .env file not found, using exported FEATURE_LINKING=${FEATURE_LINKING:-not set}"
fi
echo

# Helper function for API calls (same pattern as verify-marketplace.sh)
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
    
    if [[ "$method" == "POST" ]] || [[ "$method" == "PUT" ]] || [[ "$method" == "PATCH" ]]; then
        curl_args+=(-X "$method" -H "Content-Type: application/json")
        if [[ -n "$data" ]]; then
            curl_args+=(-d "$data")
        fi
    fi
    
    curl "${curl_args[@]}" "$url" || echo "000"
}

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

# Step 4.1: Ensure guardrail product exists
echo "  Ensuring guardrail product exists..."
ENSURE_SCRIPT="$ROOT_DIR/scripts/_guardrail/ensure_guardrail_product.php"
if [[ ! -f "$ENSURE_SCRIPT" ]]; then
    echo "  ✗ Guardrail helper script not found: $ENSURE_SCRIPT"
    exit 1
fi

set +e
PRODUCT_OUTPUT="$(php "$ENSURE_SCRIPT" 2>&1)"
PRODUCT_EXIT=$?
set -e

if [ $PRODUCT_EXIT -ne 0 ]; then
    echo "  ✗ Product setup failed"
    echo "$PRODUCT_OUTPUT" | grep -E "ERROR|Exception" | head -5 || echo "  ERROR: Failed to setup product"
    exit 1
fi

PRODUCT_ID="$(echo "$PRODUCT_OUTPUT" | tail -1 | tr -d "\r\n")"
if [ -z "$PRODUCT_ID" ]; then
    echo "  ✗ Product setup failed: empty product ID"
    exit 1
fi

echo "  ✓ Product ID: ${PRODUCT_ID}"

# Step 4.2: Ensure inventory
echo "  Ensuring inventory availability..."
set +e
INVENTORY_SETUP="$(php "$ROOT_DIR/scripts/_guardrail/ensure_inventory.php" "${PRODUCT_ID}" 2>/dev/null | tail -1)"
set -e

if [[ "$INVENTORY_SETUP" != "ok" ]]; then
    echo "  ✗ Could not setup inventory: ${INVENTORY_SETUP}"
    echo "  ✗ Order creation cannot proceed without inventory"
    exit 1
fi

echo "  ✓ Inventory ensured"

# Step 4.3: Create order via API
echo "  Creating order via API..."
IDEMPOTENCY_KEY="linking-test-$(date +%s)-$$"
ORDER_PAYLOAD="{\"currency\":\"USD\",\"items\":[{\"product_id\":\"${PRODUCT_ID}\",\"quantity\":1}],\"idempotency_key\":\"${IDEMPOTENCY_KEY}\"}"

set +e
ORDER_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/orders" "$TOKEN" "POST" "${ORDER_PAYLOAD}")"
ORDER_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$ORDER_CODE" != "201" ]]; then
    echo "  ✗ Order creation failed: HTTP ${ORDER_CODE}"
    echo "$ORDER_BODY" | head -20
    exit 1
fi

ORDER_ID="$(echo "$ORDER_BODY" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"
if [ -z "$ORDER_ID" ]; then
    echo "  ✗ Order creation failed: could not extract order ID"
    echo "$ORDER_BODY" | head -20
    exit 1
fi

echo "  ✓ Order ID: ${ORDER_ID}"
echo

# Test 5: Ensure NFT exists
echo "Test 5: Ensuring NFT exists..."
set +e
NFT_DATA="{\"contract\":\"imdc-test\",\"token_id\":\"linking-test-$(date +%s)\",\"owner_user_id\":1,\"metadata_uri\":\"ipfs://test\"}"
NFT_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/nfts/mint" "$TOKEN" "POST" "${NFT_DATA}")"
NFT_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$NFT_CODE" != "201" ]] && [[ "$NFT_CODE" != "200" ]]; then
    echo "✗ NFT mint failed: HTTP ${NFT_CODE}"
    echo "$NFT_BODY" | head -20
    exit 1
fi

NFT_ID="$(echo "$NFT_BODY" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"
if [ -z "$NFT_ID" ]; then
    # Try parsing JSON structure
    NFT_ID="$(echo "$NFT_BODY" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['id'] ?? '';")"
fi

if [ -z "$NFT_ID" ]; then
    echo "✗ NFT mint failed: could not extract NFT ID"
    echo "$NFT_BODY" | head -20
    exit 1
fi

echo "✓ NFT exists: ${NFT_ID}"
echo

# Test 6: Create links
echo "Test 6: Creating links..."
set +e

# DID-Order link
DID_ORDER_DATA="{\"did_id\":\"${DID_ID}\",\"order_id\":\"${ORDER_ID}\",\"scope\":\"ownership\"}"
DID_ORDER_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/linking/did-order" "$TOKEN" "POST" "${DID_ORDER_DATA}")"
if [[ "$DID_ORDER_CODE" != "201" ]] && [[ "$DID_ORDER_CODE" != "200" ]]; then
    echo "✗ DID-Order link failed: HTTP ${DID_ORDER_CODE}"
    cat "$TMP_BODY" | head -20
    exit 1
fi
echo "✓ DID-Order link created"

# DID-NFT link
DID_NFT_DATA="{\"did_id\":\"${DID_ID}\",\"nft_id\":\"${NFT_ID}\",\"role\":\"owner\"}"
DID_NFT_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/linking/did-nft" "$TOKEN" "POST" "${DID_NFT_DATA}")"
if [[ "$DID_NFT_CODE" != "201" ]] && [[ "$DID_NFT_CODE" != "200" ]]; then
    echo "✗ DID-NFT link failed: HTTP ${DID_NFT_CODE}"
    cat "$TMP_BODY" | head -20
    exit 1
fi
echo "✓ DID-NFT link created"

# Order-NFT link
ORDER_NFT_DATA="{\"order_id\":\"${ORDER_ID}\",\"nft_id\":\"${NFT_ID}\",\"purpose\":\"fulfillment\"}"
ORDER_NFT_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/linking/order-nft" "$TOKEN" "POST" "${ORDER_NFT_DATA}")"
if [[ "$ORDER_NFT_CODE" != "201" ]] && [[ "$ORDER_NFT_CODE" != "200" ]]; then
    echo "✗ Order-NFT link failed: HTTP ${ORDER_NFT_CODE}"
    cat "$TMP_BODY" | head -20
    exit 1
fi
echo "✓ Order-NFT link created"
echo

# Test 7: Test idempotency (re-run same links)
echo "Test 7: Testing idempotency..."
set +e

DID_ORDER_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/linking/did-order" "$TOKEN" "POST" "${DID_ORDER_DATA}")"
if [[ "$DID_ORDER_CODE2" != "200" ]] && [[ "$DID_ORDER_CODE2" != "201" ]]; then
    echo "✗ DID-Order idempotency failed: HTTP ${DID_ORDER_CODE2}"
    exit 1
fi
echo "✓ DID-Order link idempotent"

DID_NFT_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/linking/did-nft" "$TOKEN" "POST" "${DID_NFT_DATA}")"
if [[ "$DID_NFT_CODE2" != "200" ]] && [[ "$DID_NFT_CODE2" != "201" ]]; then
    echo "✗ DID-NFT idempotency failed: HTTP ${DID_NFT_CODE2}"
    exit 1
fi
echo "✓ DID-NFT link idempotent"

ORDER_NFT_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/linking/order-nft" "$TOKEN" "POST" "${ORDER_NFT_DATA}")"
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
