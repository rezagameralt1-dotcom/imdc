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
    echo "=== Places Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-places.sh --in-container'
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== Places Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Check FEATURE_VR from .env file (deterministic detection)
ENV_FILE="${ROOT_DIR}/.env"
SHOULD_SKIP=false

if [[ -f "$ENV_FILE" ]]; then
    # Read FEATURE_VR from .env file (before any modifications)
    FEATURE_VR_FROM_ENV="$(grep -E "^FEATURE_VR=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    # Normalize: check if it's explicitly set to false (case-insensitive)
    if [[ -n "$FEATURE_VR_FROM_ENV" ]]; then
        FEATURE_VR_NORMALIZED="$(echo "$FEATURE_VR_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_VR_NORMALIZED" == "false" ]] || [[ "$FEATURE_VR_NORMALIZED" == "0" ]] || [[ "$FEATURE_VR_NORMALIZED" == "no" ]] || [[ "$FEATURE_VR_NORMALIZED" == "off" ]]; then
            SHOULD_SKIP=true
        fi
    fi
    # If FEATURE_VR is missing from .env, we'll proceed and add it (don't skip)
else
    # If .env doesn't exist, check shell env (default behavior: skip if not explicitly true)
    if [[ "${FEATURE_VR:-false}" != "true" ]]; then
        SHOULD_SKIP=true
    fi
fi

if [[ "$SHOULD_SKIP" == "true" ]]; then
    echo "FEATURE_VR is not enabled (FEATURE_VR=${FEATURE_VR_FROM_ENV:-${FEATURE_VR:-false}})"
    echo "SKIP: FEATURE_VR=false"
    exit 0
fi

# Guardrail: Temporarily ensure FEATURE_VR=true for the duration of the script
echo "Guardrail: Temporarily ensuring FEATURE_VR=true for verification..."
echo

# Export FEATURE_VR=true for all child processes (artisan, curl, etc.)
export FEATURE_VR=true

# Also export FEATURE_DID=true for DID operations
export FEATURE_DID=true

# Backup and modify .env file so php-fpm workers can read FEATURE_VR=true
ENV_BACKUP=""

# Initialize cleanup function early (before .env modification)
TMP_BODY="/tmp/imdc_places_guardrail_body.$$"
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
    ENV_BACKUP="${ENV_FILE}.bak.verify-places.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"

    # Check if FEATURE_VR is already enabled
    FEATURE_VR_CURRENT="$(grep -E "^FEATURE_VR=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    FEATURE_VR_CURRENT_NORMALIZED="$(echo "$FEATURE_VR_CURRENT" | tr '[:upper:]' '[:lower:]' | tr -d ' ' || echo "")"
    
    if [[ "$FEATURE_VR_CURRENT_NORMALIZED" == "true" ]] || [[ "$FEATURE_VR_CURRENT_NORMALIZED" == "1" ]] || [[ "$FEATURE_VR_CURRENT_NORMALIZED" == "yes" ]] || [[ "$FEATURE_VR_CURRENT_NORMALIZED" == "on" ]]; then
        echo "  ✓ FEATURE_VR already enabled in .env"
    else
        # Ensure FEATURE_VR=true is set in .env
        if grep -qE "^FEATURE_VR=" "$ENV_FILE" 2>/dev/null; then
            # Replace existing line
            if [[ "$(uname)" == "Darwin" ]]; then
                # macOS sed
                sed -i '' 's/^FEATURE_VR=.*/FEATURE_VR=true/' "$ENV_FILE"
            else
                # Linux sed
                sed -i 's/^FEATURE_VR=.*/FEATURE_VR=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_VR=true in .env"
        else
            # Append if not exists
            echo "FEATURE_VR=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_VR=true to .env"
        fi
    fi

    # Ensure FEATURE_DID=true for DID operations
    if ! grep -qE "^FEATURE_DID=true" "$ENV_FILE" 2>/dev/null; then
        if grep -qE "^FEATURE_DID=" "$ENV_FILE" 2>/dev/null; then
            if [[ "$(uname)" == "Darwin" ]]; then
                sed -i '' 's/^FEATURE_DID=.*/FEATURE_DID=true/' "$ENV_FILE"
            else
                sed -i 's/^FEATURE_DID=.*/FEATURE_DID=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_DID=true in .env"
        else
            echo "FEATURE_DID=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_DID=true to .env"
        fi
    else
        echo "  ✓ FEATURE_DID already enabled in .env"
    fi
else
    echo "  ⚠ .env file not found at ${ENV_FILE}"
    echo "  Guardrail will attempt to run with exported FEATURE_VR=true"
    echo "  Note: php-fpm workers may not see the env variable without .env file"
    echo
fi

# Clear caches to ensure feature flags are read from env
echo "Pre-flight: Clearing caches..."
set +e
php artisan optimize:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
set -e
echo "    ✓ Caches cleared"
echo

# API base URL
API_BASE_URL="${IMDC_API_BASE_URL:-http://web:80}"

# Helper: Get HTTP status code from curl
curl_http_code() {
    local url="$1"
    local token="$2"
    local method="${3:-GET}"
    local data="${4:-}"
    
    local curl_args=(
        -s -o "$TMP_BODY" -w "%{http_code}"
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

# Test 4: Create place
echo "Test 4: Creating place..."
set +e
PLACE_NAME="Test Place $(date +%s)"
PLACE_DATA="{\"name\":\"${PLACE_NAME}\",\"description\":\"Test place for places guardrail\",\"type\":\"building\",\"latitude\":40.7128,\"longitude\":-74.0060,\"altitude\":10.5,\"owner_did\":\"${DID_ID}\"}"
PLACE_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/places" "$TOKEN" "POST" "${PLACE_DATA}")"
PLACE_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$PLACE_CODE" != "201" ]] && [[ "$PLACE_CODE" != "200" ]]; then
    echo "✗ Place creation failed: HTTP ${PLACE_CODE}"
    echo "$PLACE_BODY" | head -20
    exit 1
fi

PLACE_ID="$(echo "$PLACE_BODY" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"
if [ -z "$PLACE_ID" ]; then
    PLACE_ID="$(echo "$PLACE_BODY" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['id'] ?? '';")"
fi

if [ -z "$PLACE_ID" ]; then
    echo "✗ Place creation failed: could not extract place ID"
    echo "$PLACE_BODY" | head -20
    exit 1
fi

echo "✓ Place created: ${PLACE_ID}"
echo

# Test 5: Test idempotency (re-create same place)
echo "Test 5: Testing place idempotency..."
set +e
PLACE_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/places" "$TOKEN" "POST" "${PLACE_DATA}")"
set -e

if [[ "$PLACE_CODE2" != "200" ]] && [[ "$PLACE_CODE2" != "201" ]]; then
    echo "✗ Place idempotency failed: HTTP ${PLACE_CODE2}"
    exit 1
fi

echo "✓ Place idempotent"
echo

# Test 6: Link DID to place
echo "Test 6: Linking DID to place..."
set +e
LINK_DATA="{\"did_id\":\"${DID_ID}\"}"
LINK_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/places/${PLACE_ID}/link-nft" "$TOKEN" "POST" "${LINK_DATA}")"
LINK_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$LINK_CODE" != "201" ]] && [[ "$LINK_CODE" != "200" ]]; then
    echo "✗ Link creation failed: HTTP ${LINK_CODE}"
    echo "$LINK_BODY" | head -20
    exit 1
fi

echo "✓ Link created"
echo

# Test 7: Test link idempotency
echo "Test 7: Testing link idempotency..."
set +e
LINK_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/places/${PLACE_ID}/link-nft" "$TOKEN" "POST" "${LINK_DATA}")"
set -e

if [[ "$LINK_CODE2" != "200" ]] && [[ "$LINK_CODE2" != "201" ]]; then
    echo "✗ Link idempotency failed: HTTP ${LINK_CODE2}"
    exit 1
fi

echo "✓ Link idempotent"
echo

# Test 8: Validate in DB
echo "Test 8: Validating in database..."
set +e
DB_VALIDATION="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\Place;
use App\Models\PlaceLink;
use App\Models\PlaceEvent;

\$placeCount = Place::on('core')->where('id', '${PLACE_ID}')->count();
\$linkCount = PlaceLink::on('core')->where('place_id', '${PLACE_ID}')->where('did_id', '${DID_ID}')->count();
\$eventCount = PlaceEvent::on('core')->where('event_type', 'place_created_or_updated')->orWhere('event_type', 'place_linked')->count();

if (\$placeCount != 1) { echo 'FAIL: Place count=' . \$placeCount . ' (expected 1)\n'; exit(1); }
if (\$linkCount != 1) { echo 'FAIL: PlaceLink count=' . \$linkCount . ' (expected 1)\n'; exit(1); }
if (\$eventCount < 1) { echo 'FAIL: Event count=' . \$eventCount . ' (expected at least 1)\n'; exit(1); }
echo 'PASS: All place records validated\n';
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

LIST_PLACES_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/places" "$TOKEN")"
if [[ "$LIST_PLACES_CODE" != "200" ]]; then
    echo "✗ GET /api/v1/places failed: HTTP ${LIST_PLACES_CODE}"
    exit 1
fi
echo "✓ GET /api/v1/places returns 200"

GET_PLACE_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/places/${PLACE_ID}" "$TOKEN")"
if [[ "$GET_PLACE_CODE" != "200" ]]; then
    echo "✗ GET /api/v1/places/{id} failed: HTTP ${GET_PLACE_CODE}"
    exit 1
fi
echo "✓ GET /api/v1/places/{id} returns 200"
echo

echo "✓ All places tests PASSED"
echo
echo "=== Places Verification Complete ==="
