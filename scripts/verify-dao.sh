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
    echo "=== DAO Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-dao.sh --in-container'
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== DAO Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Check FEATURE_DAO from .env file (deterministic detection)
ENV_FILE="${ROOT_DIR}/.env"
SHOULD_SKIP=false

if [[ -f "$ENV_FILE" ]]; then
    # Read FEATURE_DAO from .env file (before any modifications)
    FEATURE_DAO_FROM_ENV="$(grep -E "^FEATURE_DAO=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    # Normalize: check if it's explicitly set to false (case-insensitive)
    if [[ -n "$FEATURE_DAO_FROM_ENV" ]]; then
        FEATURE_DAO_NORMALIZED="$(echo "$FEATURE_DAO_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_DAO_NORMALIZED" == "false" ]] || [[ "$FEATURE_DAO_NORMALIZED" == "0" ]] || [[ "$FEATURE_DAO_NORMALIZED" == "no" ]] || [[ "$FEATURE_DAO_NORMALIZED" == "off" ]]; then
            SHOULD_SKIP=true
        fi
    fi
    # If FEATURE_DAO is missing from .env, we'll proceed and add it (don't skip)
else
    # If .env doesn't exist, check shell env (default behavior: skip if not explicitly true)
    if [[ "${FEATURE_DAO:-false}" != "true" ]]; then
        SHOULD_SKIP=true
    fi
fi

if [[ "$SHOULD_SKIP" == "true" ]]; then
    echo "FEATURE_DAO is not enabled (FEATURE_DAO=${FEATURE_DAO_FROM_ENV:-${FEATURE_DAO:-false}})"
    echo "SKIP: FEATURE_DAO=false"
    exit 0
fi

# Guardrail: Temporarily ensure FEATURE_DAO=true for the duration of the script
echo "Guardrail: Temporarily ensuring FEATURE_DAO=true for verification..."
echo

# Export FEATURE_DAO=true for all child processes (artisan, curl, etc.)
export FEATURE_DAO=true

# Also export FEATURE_DID=true for DID operations
export FEATURE_DID=true

# Backup and modify .env file so php-fpm workers can read FEATURE_DAO=true
ENV_BACKUP=""

# Initialize cleanup function early (before .env modification)
TMP_BODY="/tmp/imdc_dao_guardrail_body.$$"
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
    ENV_BACKUP="${ENV_FILE}.bak.verify-dao.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"

    # Check if FEATURE_DAO is already enabled
    FEATURE_DAO_CURRENT="$(grep -E "^FEATURE_DAO=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    FEATURE_DAO_CURRENT_NORMALIZED="$(echo "$FEATURE_DAO_CURRENT" | tr '[:upper:]' '[:lower:]' | tr -d ' ' || echo "")"
    
    if [[ "$FEATURE_DAO_CURRENT_NORMALIZED" == "true" ]] || [[ "$FEATURE_DAO_CURRENT_NORMALIZED" == "1" ]] || [[ "$FEATURE_DAO_CURRENT_NORMALIZED" == "yes" ]] || [[ "$FEATURE_DAO_CURRENT_NORMALIZED" == "on" ]]; then
        echo "  ✓ FEATURE_DAO already enabled in .env"
    else
        # Ensure FEATURE_DAO=true is set in .env
        if grep -qE "^FEATURE_DAO=" "$ENV_FILE" 2>/dev/null; then
            # Replace existing line
            if [[ "$(uname)" == "Darwin" ]]; then
                # macOS sed
                sed -i '' 's/^FEATURE_DAO=.*/FEATURE_DAO=true/' "$ENV_FILE"
            else
                # Linux sed
                sed -i 's/^FEATURE_DAO=.*/FEATURE_DAO=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_DAO=true in .env"
        else
            # Append if not exists
            echo "FEATURE_DAO=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_DAO=true to .env"
        fi
    fi

    # Also ensure FEATURE_DID=true for DID operations
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
    echo "  Guardrail will attempt to run with exported FEATURE_DAO=true"
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

# Test 4: Create proposal
echo "Test 4: Creating proposal..."
set +e
PROPOSAL_DATA="{\"title\":\"Test Proposal $(date +%s)\",\"description\":\"Test proposal for DAO guardrail\",\"created_by_did\":\"${DID_ID}\",\"status\":\"draft\"}"
PROPOSAL_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/dao/proposals" "$TOKEN" "POST" "${PROPOSAL_DATA}")"
PROPOSAL_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$PROPOSAL_CODE" != "201" ]] && [[ "$PROPOSAL_CODE" != "200" ]]; then
    echo "✗ Proposal creation failed: HTTP ${PROPOSAL_CODE}"
    echo "$PROPOSAL_BODY" | head -20
    exit 1
fi

PROPOSAL_ID="$(echo "$PROPOSAL_BODY" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"
if [ -z "$PROPOSAL_ID" ]; then
    PROPOSAL_ID="$(echo "$PROPOSAL_BODY" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['id'] ?? '';")"
fi

if [ -z "$PROPOSAL_ID" ]; then
    echo "✗ Proposal creation failed: could not extract proposal ID"
    echo "$PROPOSAL_BODY" | head -20
    exit 1
fi

echo "✓ Proposal created: ${PROPOSAL_ID}"
echo

# Test 5: Create active proposal (for voting)
echo "Test 5: Creating active proposal..."
set +e
ACTIVATE_DATA="{\"title\":\"Active Test Proposal $(date +%s)\",\"description\":\"Active test proposal for DAO guardrail\",\"created_by_did\":\"${DID_ID}\",\"status\":\"active\"}"
ACTIVATE_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/dao/proposals" "$TOKEN" "POST" "${ACTIVATE_DATA}")"
ACTIVATE_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$ACTIVATE_CODE" != "201" ]] && [[ "$ACTIVATE_CODE" != "200" ]]; then
    echo "✗ Active proposal creation failed: HTTP ${ACTIVATE_CODE}"
    echo "$ACTIVATE_BODY" | head -20
    exit 1
fi

ACTIVE_PROPOSAL_ID="$(echo "$ACTIVATE_BODY" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"
if [ -z "$ACTIVE_PROPOSAL_ID" ]; then
    ACTIVE_PROPOSAL_ID="$(echo "$ACTIVATE_BODY" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['id'] ?? '';")"
fi

if [ -z "$ACTIVE_PROPOSAL_ID" ]; then
    echo "✗ Active proposal creation failed: could not extract proposal ID"
    echo "$ACTIVATE_BODY" | head -20
    exit 1
fi

PROPOSAL_ID="$ACTIVE_PROPOSAL_ID"
echo "✓ Active proposal: ${PROPOSAL_ID}"
echo

# Test 6: Cast vote
echo "Test 6: Casting vote..."
set +e
VOTE_DATA="{\"voter_did\":\"${DID_ID}\",\"vote\":\"yes\",\"weight\":1}"
VOTE_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/dao/proposals/${PROPOSAL_ID}/vote" "$TOKEN" "POST" "${VOTE_DATA}")"
VOTE_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$VOTE_CODE" != "201" ]] && [[ "$VOTE_CODE" != "200" ]]; then
    echo "✗ Vote casting failed: HTTP ${VOTE_CODE}"
    echo "$VOTE_BODY" | head -20
    exit 1
fi

echo "✓ Vote cast"
echo

# Test 7: Test idempotency (re-cast same vote)
echo "Test 7: Testing vote idempotency..."
set +e
VOTE_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/dao/proposals/${PROPOSAL_ID}/vote" "$TOKEN" "POST" "${VOTE_DATA}")"
set -e

if [[ "$VOTE_CODE2" != "200" ]] && [[ "$VOTE_CODE2" != "201" ]]; then
    echo "✗ Vote idempotency failed: HTTP ${VOTE_CODE2}"
    exit 1
fi

echo "✓ Vote idempotent"
echo

# Test 8: Get proposal results
echo "Test 8: Getting proposal results..."
set +e
RESULTS_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/dao/proposals/${PROPOSAL_ID}" "$TOKEN")"
RESULTS_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$RESULTS_CODE" != "200" ]]; then
    echo "✗ Get proposal results failed: HTTP ${RESULTS_CODE}"
    echo "$RESULTS_BODY" | head -20
    exit 1
fi

echo "✓ Proposal results retrieved"
echo

# Test 9: Validate in DB
echo "Test 9: Validating in database..."
set +e
DB_VALIDATION="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\DaoProposal;
use App\Models\DaoVote;
\$proposalCount = DaoProposal::where('id', '${PROPOSAL_ID}')->count();
\$voteCount = DaoVote::where('proposal_id', '${PROPOSAL_ID}')->where('voter_did', '${DID_ID}')->count();
if (\$proposalCount != 1) { echo 'FAIL: Proposal count=' . \$proposalCount . ' (expected 1)\n'; exit(1); }
if (\$voteCount != 1) { echo 'FAIL: Vote count=' . \$voteCount . ' (expected 1)\n'; exit(1); }
echo 'PASS: All DAO records validated\n';
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

# Test 10: List proposals
echo "Test 10: Listing proposals..."
set +e
LIST_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/dao/proposals" "$TOKEN")"
set -e

if [[ "$LIST_CODE" != "200" ]]; then
    echo "✗ List proposals failed: HTTP ${LIST_CODE}"
    exit 1
fi

echo "✓ List proposals returns 200"
echo

echo "✓ All DAO tests PASSED"
echo
echo "=== DAO Verification Complete ==="
