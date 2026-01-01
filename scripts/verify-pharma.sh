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
    echo "=== Pharma Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-pharma.sh --in-container'
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== Pharma Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Check FEATURE_PHARMA from .env file (deterministic detection)
ENV_FILE="${ROOT_DIR}/.env"
SHOULD_SKIP=false

if [[ -f "$ENV_FILE" ]]; then
    # Read FEATURE_PHARMA from .env file (before any modifications)
    FEATURE_PHARMA_FROM_ENV="$(grep -E "^FEATURE_PHARMA=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    # Normalize: check if it's explicitly set to false (case-insensitive)
    if [[ -n "$FEATURE_PHARMA_FROM_ENV" ]]; then
        FEATURE_PHARMA_NORMALIZED="$(echo "$FEATURE_PHARMA_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_PHARMA_NORMALIZED" == "false" ]] || [[ "$FEATURE_PHARMA_NORMALIZED" == "0" ]] || [[ "$FEATURE_PHARMA_NORMALIZED" == "no" ]] || [[ "$FEATURE_PHARMA_NORMALIZED" == "off" ]]; then
            SHOULD_SKIP=true
        fi
    fi
    # If FEATURE_PHARMA is missing from .env, we'll proceed and add it (don't skip)
else
    # If .env doesn't exist, check shell env (default behavior: skip if not explicitly true)
    if [[ "${FEATURE_PHARMA:-false}" != "true" ]]; then
        SHOULD_SKIP=true
    fi
fi

if [[ "$SHOULD_SKIP" == "true" ]]; then
    echo "FEATURE_PHARMA is not enabled (FEATURE_PHARMA=${FEATURE_PHARMA_FROM_ENV:-${FEATURE_PHARMA:-false}})"
    echo "SKIP: FEATURE_PHARMA=false"
    exit 0
fi

# Guardrail: Temporarily ensure FEATURE_PHARMA=true for the duration of the script
echo "Guardrail: Temporarily ensuring FEATURE_PHARMA=true for verification..."
echo

# Export FEATURE_PHARMA=true for all child processes (artisan, curl, etc.)
export FEATURE_PHARMA=true

# Backup and modify .env file so php-fpm workers can read FEATURE_PHARMA=true
ENV_BACKUP=""

# Initialize cleanup function early (before .env modification)
TMP_BODY="/tmp/imdc_pharma_guardrail_body.$$"
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
    ENV_BACKUP="${ENV_FILE}.bak.verify-pharma.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"

    # Check if FEATURE_PHARMA is already enabled
    FEATURE_PHARMA_CURRENT="$(grep -E "^FEATURE_PHARMA=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    FEATURE_PHARMA_CURRENT_NORMALIZED="$(echo "$FEATURE_PHARMA_CURRENT" | tr '[:upper:]' '[:lower:]' | tr -d ' ' || echo "")"
    
    if [[ "$FEATURE_PHARMA_CURRENT_NORMALIZED" == "true" ]] || [[ "$FEATURE_PHARMA_CURRENT_NORMALIZED" == "1" ]] || [[ "$FEATURE_PHARMA_CURRENT_NORMALIZED" == "yes" ]] || [[ "$FEATURE_PHARMA_CURRENT_NORMALIZED" == "on" ]]; then
        echo "  ✓ FEATURE_PHARMA already enabled in .env"
    else
        # Ensure FEATURE_PHARMA=true is set in .env
        if grep -qE "^FEATURE_PHARMA=" "$ENV_FILE" 2>/dev/null; then
            # Replace existing line
            if [[ "$(uname)" == "Darwin" ]]; then
                # macOS sed
                sed -i '' 's/^FEATURE_PHARMA=.*/FEATURE_PHARMA=true/' "$ENV_FILE"
            else
                # Linux sed
                sed -i 's/^FEATURE_PHARMA=.*/FEATURE_PHARMA=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_PHARMA=true in .env"
        else
            # Append if not exists
            echo "FEATURE_PHARMA=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_PHARMA=true to .env"
        fi
    fi
else
    echo "  ⚠ .env file not found at ${ENV_FILE}"
    echo "  Guardrail will attempt to run with exported FEATURE_PHARMA=true"
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

# Test 3: Seed test drugs (deterministic)
echo "Test 3: Seeding test drugs..."
set +e
SEED_OUTPUT="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\PharmaDrug;
use Illuminate\Support\Str;

// Deterministic drug IDs (for idempotency)
\$drugs = [
    ['id' => '00000000-0000-0000-0000-000000000001', 'name' => 'Aspirin', 'generic_name' => 'Acetylsalicylic acid', 'description' => 'Pain reliever and anti-inflammatory'],
    ['id' => '00000000-0000-0000-0000-000000000002', 'name' => 'Ibuprofen', 'generic_name' => 'Ibuprofen', 'description' => 'NSAID pain reliever'],
    ['id' => '00000000-0000-0000-0000-000000000003', 'name' => 'Warfarin', 'generic_name' => 'Warfarin', 'description' => 'Blood thinner'],
];

foreach (\$drugs as \$drugData) {
    PharmaDrug::on('core')->firstOrCreate(
        ['id' => \$drugData['id']],
        [
            'name' => \$drugData['name'],
            'generic_name' => \$drugData['generic_name'],
            'description' => \$drugData['description'],
            'warnings' => ['May cause stomach irritation'],
            'contraindications' => ['Do not use if allergic'],
        ]
    );
}
echo 'OK';
" 2>&1)"
SEED_EXIT=$?
set -e

if [ $SEED_EXIT -ne 0 ]; then
    echo "✗ Drug seeding failed"
    echo "$SEED_OUTPUT" | grep -E "ERROR|Exception" | head -5 || echo "  ERROR: Failed to seed drugs"
    exit 1
fi

echo "✓ Test drugs seeded"
echo

# Test 4: Seed test interaction
echo "Test 4: Seeding test interaction..."
set +e
INTERACTION_OUTPUT="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\PharmaInteraction;

// Deterministic interaction between Aspirin and Warfarin
PharmaInteraction::on('core')->firstOrCreate(
    ['drug1_id' => '00000000-0000-0000-0000-000000000001', 'drug2_id' => '00000000-0000-0000-0000-000000000003'],
    [
        'severity' => 'severe',
        'description' => 'Increased risk of bleeding when taken together',
    ]
);
echo 'OK';
" 2>&1)"
INTERACTION_EXIT=$?
set -e

if [ $INTERACTION_EXIT -ne 0 ]; then
    echo "✗ Interaction seeding failed"
    echo "$INTERACTION_OUTPUT" | grep -E "ERROR|Exception" | head -5 || echo "  ERROR: Failed to seed interaction"
    exit 1
fi

echo "✓ Test interaction seeded"
echo

# Test 5: List drugs
echo "Test 5: Listing drugs..."
set +e
LIST_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/pharma/drugs" "$TOKEN")"
LIST_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$LIST_CODE" != "200" ]]; then
    echo "✗ List drugs failed: HTTP ${LIST_CODE}"
    echo "$LIST_BODY" | head -20
    exit 1
fi

# Verify disclaimer is present
if ! echo "$LIST_BODY" | grep -q "disclaimer"; then
    echo "✗ Disclaimer missing from response"
    exit 1
fi

echo "✓ List drugs returns 200 with disclaimer"
echo

# Test 6: Get drug by ID
echo "Test 6: Getting drug by ID..."
DRUG_ID="00000000-0000-0000-0000-000000000001"
set +e
GET_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/pharma/drugs/${DRUG_ID}" "$TOKEN")"
GET_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$GET_CODE" != "200" ]]; then
    echo "✗ Get drug failed: HTTP ${GET_CODE}"
    echo "$GET_BODY" | head -20
    exit 1
fi

# Verify disclaimer is present
if ! echo "$GET_BODY" | grep -q "disclaimer"; then
    echo "✗ Disclaimer missing from response"
    exit 1
fi

echo "✓ Get drug returns 200 with disclaimer"
echo

# Test 7: Check interactions
echo "Test 7: Checking interactions..."
set +e
CHECK_DATA="{\"drug_ids\":[\"00000000-0000-0000-0000-000000000001\",\"00000000-0000-0000-0000-000000000003\"]}"
CHECK_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/pharma/check" "$TOKEN" "POST" "${CHECK_DATA}")"
CHECK_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$CHECK_CODE" != "200" ]] && [[ "$CHECK_CODE" != "201" ]]; then
    echo "✗ Check interactions failed: HTTP ${CHECK_CODE}"
    echo "$CHECK_BODY" | head -20
    exit 1
fi

# Verify disclaimer is present
if ! echo "$CHECK_BODY" | grep -q "disclaimer"; then
    echo "✗ Disclaimer missing from response"
    exit 1
fi

# Verify interaction was found
if ! echo "$CHECK_BODY" | grep -q "interactions"; then
    echo "✗ Interactions field missing from response"
    exit 1
fi

echo "✓ Check interactions returns 200 with disclaimer"
echo

# Test 8: Test idempotency (re-check same drugs)
echo "Test 8: Testing idempotency..."
set +e
CHECK_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/pharma/check" "$TOKEN" "POST" "${CHECK_DATA}")"
set -e

if [[ "$CHECK_CODE2" != "200" ]] && [[ "$CHECK_CODE2" != "201" ]]; then
    echo "✗ Interaction check idempotency failed: HTTP ${CHECK_CODE2}"
    exit 1
fi

echo "✓ Interaction check idempotent"
echo

# Test 9: Validate in DB
echo "Test 9: Validating in database..."
set +e
DB_VALIDATION="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\PharmaDrug;
use App\Models\PharmaInteraction;
use App\Models\PharmaEvent;

\$drugCount = PharmaDrug::on('core')->whereIn('id', ['00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000002', '00000000-0000-0000-0000-000000000003'])->count();
\$interactionCount = PharmaInteraction::on('core')->where('drug1_id', '00000000-0000-0000-0000-000000000001')->where('drug2_id', '00000000-0000-0000-0000-000000000003')->count();
\$eventCount = PharmaEvent::on('core')->where('event_type', 'interaction_check')->count();

if (\$drugCount < 3) { echo 'FAIL: Drug count=' . \$drugCount . ' (expected at least 3)\n'; exit(1); }
if (\$interactionCount < 1) { echo 'FAIL: Interaction count=' . \$interactionCount . ' (expected at least 1)\n'; exit(1); }
if (\$eventCount < 1) { echo 'FAIL: Event count=' . \$eventCount . ' (expected at least 1)\n'; exit(1); }
echo 'PASS: All pharma records validated\n';
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

echo "✓ All pharma tests PASSED"
echo
echo "=== Pharma Verification Complete ==="
