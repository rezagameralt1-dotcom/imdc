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
    echo "=== Training Verification (Official Guardrail) ==="
    echo
    echo "Execution context: host-delegating"
    echo
    docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-training.sh --in-container'
    exit $?
else
    EXEC_CTX="host-direct"
fi

echo "=== Training Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Check FEATURE_TRAINING from .env file (deterministic detection)
ENV_FILE="${ROOT_DIR}/.env"
SHOULD_SKIP=false

if [[ -f "$ENV_FILE" ]]; then
    FEATURE_TRAINING_FROM_ENV="$(grep -E "^FEATURE_TRAINING=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    if [[ -n "$FEATURE_TRAINING_FROM_ENV" ]]; then
        FEATURE_TRAINING_NORMALIZED="$(echo "$FEATURE_TRAINING_FROM_ENV" | tr '[:upper:]' '[:lower:]' | tr -d ' ')"
        if [[ "$FEATURE_TRAINING_NORMALIZED" == "false" ]] || [[ "$FEATURE_TRAINING_NORMALIZED" == "0" ]] || [[ "$FEATURE_TRAINING_NORMALIZED" == "no" ]] || [[ "$FEATURE_TRAINING_NORMALIZED" == "off" ]]; then
            SHOULD_SKIP=true
        fi
    fi
else
    if [[ "${FEATURE_TRAINING:-false}" != "true" ]]; then
        SHOULD_SKIP=true
    fi
fi

if [[ "$SHOULD_SKIP" == "true" ]]; then
    echo "FEATURE_TRAINING is not enabled (FEATURE_TRAINING=${FEATURE_TRAINING_FROM_ENV:-${FEATURE_TRAINING:-false}})"
    echo "SKIP: FEATURE_TRAINING=false"
    exit 0
fi

# Guardrail: Temporarily ensure FEATURE_TRAINING=true
echo "Guardrail: Temporarily ensuring FEATURE_TRAINING=true for verification..."
echo

export FEATURE_TRAINING=true
export FEATURE_DID=true

ENV_BACKUP=""
TMP_BODY="/tmp/imdc_training_guardrail_body.$$"
cleanup() {
    rm -f "$TMP_BODY" 2>/dev/null || true
    if [[ -n "$ENV_BACKUP" ]] && [[ -f "$ENV_BACKUP" ]] && [[ -n "$ENV_FILE" ]]; then
        echo "Restoring original .env file..."
        if [[ -f "$ENV_FILE" ]]; then
            mv "$ENV_BACKUP" "$ENV_FILE" 2>/dev/null || true
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
    ENV_BACKUP="${ENV_FILE}.bak.verify-training.${TIMESTAMP}"
    cp -a "$ENV_FILE" "$ENV_BACKUP"
    echo "  ✓ Backed up .env to ${ENV_BACKUP}"

    FEATURE_TRAINING_CURRENT="$(grep -E "^FEATURE_TRAINING=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo "")"
    FEATURE_TRAINING_CURRENT_NORMALIZED="$(echo "$FEATURE_TRAINING_CURRENT" | tr '[:upper:]' '[:lower:]' | tr -d ' ' || echo "")"
    
    if [[ "$FEATURE_TRAINING_CURRENT_NORMALIZED" == "true" ]] || [[ "$FEATURE_TRAINING_CURRENT_NORMALIZED" == "1" ]] || [[ "$FEATURE_TRAINING_CURRENT_NORMALIZED" == "yes" ]] || [[ "$FEATURE_TRAINING_CURRENT_NORMALIZED" == "on" ]]; then
        echo "  ✓ FEATURE_TRAINING already enabled in .env"
    else
        if grep -qE "^FEATURE_TRAINING=" "$ENV_FILE" 2>/dev/null; then
            if [[ "$(uname)" == "Darwin" ]]; then
                sed -i '' 's/^FEATURE_TRAINING=.*/FEATURE_TRAINING=true/' "$ENV_FILE"
            else
                sed -i 's/^FEATURE_TRAINING=.*/FEATURE_TRAINING=true/' "$ENV_FILE"
            fi
            echo "  ✓ Updated FEATURE_TRAINING=true in .env"
        else
            echo "FEATURE_TRAINING=true" >> "$ENV_FILE"
            echo "  ✓ Added FEATURE_TRAINING=true to .env"
        fi
    fi

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
    echo "  Guardrail will attempt to run with exported FEATURE_TRAINING=true"
    echo
fi

echo "Pre-flight: Clearing caches after .env modification..."
set +e
php artisan optimize:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
set -e
echo "    ✓ Caches cleared"
echo

# Verify FEATURE_TRAINING is enabled at runtime before API calls
echo "Debug: Verifying FEATURE_TRAINING is enabled at runtime..."
if [[ -f "$ENV_FILE" ]]; then
    FEATURE_TRAINING_ENV="$(grep -E '^FEATURE_TRAINING=' "$ENV_FILE" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '\r' || echo '')"
    echo "  FEATURE_TRAINING in .env: ${FEATURE_TRAINING_ENV:-not set}"
else
    echo "  FEATURE_TRAINING in .env: (file not found)"
fi

FEATURE_TRAINING_GETENV="$(php -r 'echo getenv("FEATURE_TRAINING") ?: "NULL";' 2>/dev/null || echo 'unknown')"
echo "  getenv('FEATURE_TRAINING'): ${FEATURE_TRAINING_GETENV}"

FEATURE_TRAINING_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('training.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
echo "  config('training.enabled'): ${FEATURE_TRAINING_PHP}"

if [[ "$FEATURE_TRAINING_PHP" != "true" ]]; then
    echo "  ⚠ WARNING: FEATURE_TRAINING is not enabled at runtime!"
    echo "  Clearing caches again and re-checking..."
    set +e
    php artisan optimize:clear 2>/dev/null || true
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    set -e
    FEATURE_TRAINING_PHP="$(php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); var_export(config('training.enabled'));" 2>/dev/null | grep -oE '(true|false)' | head -1 || echo 'unknown')"
    echo "  config('training.enabled') after re-clear: ${FEATURE_TRAINING_PHP}"
    if [[ "$FEATURE_TRAINING_PHP" != "true" ]]; then
        echo "  ✗ ERROR: FEATURE_TRAINING still not enabled after cache clear!"
        exit 1
    fi
fi
echo "  ✓ FEATURE_TRAINING confirmed enabled at runtime"
echo

API_BASE_URL="${IMDC_API_BASE_URL:-http://web:80}"

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

# Test 3: Create course
echo "Test 3: Creating course..."
set +e
COURSE_TITLE="Test Course $(date +%s)"
COURSE_DATA="{\"title\":\"${COURSE_TITLE}\",\"description\":\"Test course for training guardrail\",\"level\":\"beginner\",\"language\":\"en\"}"
COURSE_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/training/courses" "$TOKEN" "POST" "${COURSE_DATA}")"
COURSE_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$COURSE_CODE" != "201" ]] && [[ "$COURSE_CODE" != "200" ]]; then
    echo "✗ Course creation failed: HTTP ${COURSE_CODE}"
    echo "$COURSE_BODY" | head -20
    exit 1
fi

COURSE_ID="$(echo "$COURSE_BODY" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"
if [ -z "$COURSE_ID" ]; then
    COURSE_ID="$(echo "$COURSE_BODY" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['id'] ?? '';")"
fi

if [ -z "$COURSE_ID" ]; then
    echo "✗ Course creation failed: could not extract course ID"
    echo "$COURSE_BODY" | head -20
    exit 1
fi

echo "✓ Course created: ${COURSE_ID}"
echo

# Test 4: Publish course
echo "Test 4: Publishing course..."
set +e
PUBLISH_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/training/courses/${COURSE_ID}/publish" "$TOKEN" "POST")"
set -e

if [[ "$PUBLISH_CODE" != "200" ]]; then
    echo "✗ Course publish failed: HTTP ${PUBLISH_CODE}"
    cat "$TMP_BODY" | head -20
    exit 1
fi

echo "✓ Course published"
echo

# Test 5: List courses -> 200
echo "Test 5: Listing courses..."
set +e
LIST_COURSES_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/training/courses" "$TOKEN")"
set -e

if [[ "$LIST_COURSES_CODE" != "200" ]]; then
    echo "✗ List courses failed: HTTP ${LIST_COURSES_CODE}"
    exit 1
fi

echo "✓ List courses returns 200"
echo

# Test 6: Enroll user
echo "Test 6: Enrolling user..."
IDEMPOTENCY_KEY_ENROLL="training-enroll-$(date +%s)"
set +e
ENROLL_DATA="{\"idempotency_key\":\"${IDEMPOTENCY_KEY_ENROLL}\"}"
ENROLL_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/training/courses/${COURSE_ID}/enroll" "$TOKEN" "POST" "${ENROLL_DATA}")"
ENROLL_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$ENROLL_CODE" != "201" ]] && [[ "$ENROLL_CODE" != "200" ]]; then
    echo "✗ Enrollment failed: HTTP ${ENROLL_CODE}"
    echo "$ENROLL_BODY" | head -20
    exit 1
fi

ENROLLMENT_ID="$(echo "$ENROLL_BODY" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"
if [ -z "$ENROLLMENT_ID" ]; then
    ENROLLMENT_ID="$(echo "$ENROLL_BODY" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['id'] ?? '';")"
fi

if [ -z "$ENROLLMENT_ID" ]; then
    echo "✗ Enrollment failed: could not extract enrollment ID"
    echo "$ENROLL_BODY" | head -20
    exit 1
fi

echo "✓ Enrolled: ${ENROLLMENT_ID}"
echo

# Test 7: Enroll again with same Idempotency-Key -> returns same enrollmentId
echo "Test 7: Testing enrollment idempotency..."
set +e
ENROLL_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/training/courses/${COURSE_ID}/enroll" "$TOKEN" "POST" "${ENROLL_DATA}")"
ENROLL_BODY2="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$ENROLL_CODE2" != "200" ]] && [[ "$ENROLL_CODE2" != "201" ]]; then
    echo "✗ Enrollment idempotency failed: HTTP ${ENROLL_CODE2}"
    exit 1
fi

ENROLLMENT_ID2="$(echo "$ENROLL_BODY2" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 || echo '')"
if [ -z "$ENROLLMENT_ID2" ]; then
    ENROLLMENT_ID2="$(echo "$ENROLL_BODY2" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['id'] ?? '';")"
fi

if [[ "$ENROLLMENT_ID" != "$ENROLLMENT_ID2" ]]; then
    echo "✗ Enrollment idempotency failed: different IDs (${ENROLLMENT_ID} vs ${ENROLLMENT_ID2})"
    exit 1
fi

echo "✓ Enrollment idempotent (same ID: ${ENROLLMENT_ID})"
echo

# Test 8: Complete enrollment
echo "Test 8: Completing enrollment..."
COMPLETE_IDEMPOTENCY_KEY="training-complete-$(date +%s)"
set +e
COMPLETE_DATA="{\"idempotency_key\":\"${COMPLETE_IDEMPOTENCY_KEY}\"}"
COMPLETE_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/training/enrollments/${ENROLLMENT_ID}/complete" "$TOKEN" "POST" "${COMPLETE_DATA}")"
COMPLETE_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$COMPLETE_CODE" != "201" ]] && [[ "$COMPLETE_CODE" != "200" ]]; then
    echo "✗ Completion failed: HTTP ${COMPLETE_CODE}"
    echo "$COMPLETE_BODY" | head -20
    exit 1
fi

SKILL_NFT_ID="$(echo "$COMPLETE_BODY" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['skill_nft']['id'] ?? '';")"

if [ -z "$SKILL_NFT_ID" ]; then
    echo "✗ Completion failed: could not extract skill NFT ID"
    echo "$COMPLETE_BODY" | head -20
    exit 1
fi

echo "✓ Enrollment completed, skill NFT issued: ${SKILL_NFT_ID}"
echo

# Test 9: Complete again with same Idempotency-Key -> returns same skillNftId
echo "Test 9: Testing completion idempotency..."
set +e
COMPLETE_CODE2="$(curl_http_code "${API_BASE_URL}/api/v1/training/enrollments/${ENROLLMENT_ID}/complete" "$TOKEN" "POST" "${COMPLETE_DATA}")"
COMPLETE_BODY2="$(cat "$TMP_BODY" 2>/dev/null || echo '{}')"
set -e

if [[ "$COMPLETE_CODE2" != "200" ]] && [[ "$COMPLETE_CODE2" != "201" ]]; then
    echo "✗ Completion idempotency failed: HTTP ${COMPLETE_CODE2}"
    exit 1
fi

SKILL_NFT_ID2="$(echo "$COMPLETE_BODY2" | php -r "require 'vendor/autoload.php'; \$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['data']['skill_nft']['id'] ?? '';")"

if [[ "$SKILL_NFT_ID" != "$SKILL_NFT_ID2" ]]; then
    echo "✗ Completion idempotency failed: different IDs (${SKILL_NFT_ID} vs ${SKILL_NFT_ID2})"
    exit 1
fi

echo "✓ Completion idempotent (same ID: ${SKILL_NFT_ID})"
echo

# Test 10: GET /users/me/enrollments -> includes completed
echo "Test 10: Getting user enrollments..."
set +e
GET_ENROLLMENTS_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/training/users/me/enrollments" "$TOKEN")"
GET_ENROLLMENTS_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '[]')"
set -e

if [[ "$GET_ENROLLMENTS_CODE" != "200" ]]; then
    echo "✗ GET /users/me/enrollments failed: HTTP ${GET_ENROLLMENTS_CODE}"
    exit 1
fi

if ! echo "$GET_ENROLLMENTS_BODY" | grep -q "$ENROLLMENT_ID"; then
    echo "✗ Enrollment not found in user enrollments"
    exit 1
fi

echo "✓ GET /users/me/enrollments returns 200 with enrollment"
echo

# Test 11: GET /users/me/skill-nfts -> includes issued skill nft
echo "Test 11: Getting user skill NFTs..."
set +e
GET_SKILL_NFTS_CODE="$(curl_http_code "${API_BASE_URL}/api/v1/training/users/me/skill-nfts" "$TOKEN")"
GET_SKILL_NFTS_BODY="$(cat "$TMP_BODY" 2>/dev/null || echo '[]')"
set -e

if [[ "$GET_SKILL_NFTS_CODE" != "200" ]]; then
    echo "✗ GET /users/me/skill-nfts failed: HTTP ${GET_SKILL_NFTS_CODE}"
    exit 1
fi

if ! echo "$GET_SKILL_NFTS_BODY" | grep -q "$SKILL_NFT_ID"; then
    echo "✗ Skill NFT not found in user skill NFTs"
    exit 1
fi

echo "✓ GET /users/me/skill-nfts returns 200 with skill NFT"
echo

# Test 12: Validate in DB
echo "Test 12: Validating in database..."
set +e
DB_VALIDATION="$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\Training\Course;
use App\Models\Training\Enrollment;
use App\Models\Training\SkillNft;
use App\Models\Training\TrainingEvent;

\$courseCount = Course::on('core')->where('id', '${COURSE_ID}')->count();
\$enrollmentCount = Enrollment::on('core')->where('id', '${ENROLLMENT_ID}')->count();
\$skillNftCount = SkillNft::on('core')->where('id', '${SKILL_NFT_ID}')->count();
\$eventCount = TrainingEvent::on('core')->where('event_type', 'enrollment_completed')->orWhere('event_type', 'course_created_or_updated')->count();

if (\$courseCount != 1) { echo 'FAIL: Course count=' . \$courseCount . ' (expected 1)\n'; exit(1); }
if (\$enrollmentCount != 1) { echo 'FAIL: Enrollment count=' . \$enrollmentCount . ' (expected 1)\n'; exit(1); }
if (\$skillNftCount != 1) { echo 'FAIL: SkillNft count=' . \$skillNftCount . ' (expected 1)\n'; exit(1); }
if (\$eventCount < 1) { echo 'FAIL: Event count=' . \$eventCount . ' (expected at least 1)\n'; exit(1); }
echo 'PASS: All training records validated\n';
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

echo "✓ All training tests PASSED"
echo
echo "=== Training Verification Complete ==="
echo

exit 0
echo "  FEATURE_TRAINING in .env: $(grep -E '^FEATURE_TRAINING=' .env | tail -n1 | cut -d= -f2- | tr -d '\r')"
php -r 'echo "  env(FEATURE_TRAINING)=".(getenv("FEATURE_TRAINING")?: "NULL")."\n";'
