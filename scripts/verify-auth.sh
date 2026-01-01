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

# Determine execution context (deterministic: single decision at startup)
if is_container "${1:-}"; then
  EXEC_CTX="container"
elif has_docker_compose; then
  # Host mode: delegate everything to container and exit
  echo "=== Authentication Verification (Official Guardrail) ==="
  echo
  echo "Execution context: host-delegating"
  echo
  # Delegate to container: always use /var/www/html (container path)
  docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-auth.sh --in-container'
  exit $?
else
  EXEC_CTX="host-direct"
fi

# From here on, we're running in container context
echo "=== Authentication Verification (Official Guardrail) ==="
echo
echo "Execution context: ${EXEC_CTX}"
echo

# Helper: Probe URL and return HTTP status code
probe_url() {
  local url="$1"
  set +e
  local code
  code="$(curl -sS -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 5 "$url" 2>/dev/null || echo "000000")"
  set -e
  echo "$code"
}

# Determine API base URL based on context
API_BASE_URL="http://127.0.0.1:8080"
if [[ "${EXEC_CTX}" == "container" ]]; then
  # In container, probe Docker service URLs in priority order
  declare -a URL_CANDIDATES=(
    "http://web:80"
    "http://nginx:80"
    "http://app:8000"
    "http://127.0.0.1:8080"
    "http://host.docker.internal:8080"
  )
  
  API_BASE_URL=""
  ATTEMPTED_URLS=()
  
  for candidate in "${URL_CANDIDATES[@]}"; do
    ATTEMPTED_URLS+=("$candidate")
    CODE="$(probe_url "${candidate}/")"
    # Accept HTTP 200-399 as valid
    if [[ "$CODE" =~ ^[23][0-9][0-9]$ ]]; then
      API_BASE_URL="$candidate"
      break
    fi
  done
  
  if [[ -z "$API_BASE_URL" ]]; then
    echo "✗ Failed to resolve API base URL in container"
    echo "  Attempted URLs:"
    for url in "${ATTEMPTED_URLS[@]}"; do
      echo "    - $url"
    done
    echo
    exit 1
  fi
fi

echo "API Base URL: ${API_BASE_URL}"
echo

TMP_BODY="/tmp/imdc_auth_guardrail_body.$$"
cleanup() { rm -f "$TMP_BODY" 2>/dev/null || true; }
trap cleanup EXIT

curl_http_code() {
  local url="$1"
  curl -sS -o "$TMP_BODY" -w "%{http_code}" \
    --connect-timeout 2 --max-time 10 \
    -H "Accept: application/json" \
    "$url" || echo "000000"
}

echo "Test 1: Asserting web is reachable on ${API_BASE_URL}/..."
CODE="$(curl_http_code "${API_BASE_URL}/")"
if [[ "${CODE}" == "200" || "${CODE}" == "301" || "${CODE}" == "302" ]]; then
  echo "✓ Web is reachable (HTTP ${CODE})"
else
  echo "✗ Web returned unexpected HTTP ${CODE}"
  if [[ "${CODE}" == "000000" ]]; then
    echo "  Diagnostic: curl could not connect to ${API_BASE_URL} (check container/port/network)."
  fi
  echo
  exit 1
fi
echo

echo "Test 2: Asserting /api/me returns 401 without token..."
CODE="$(curl_http_code "${API_BASE_URL}/api/me")"
if [[ "${CODE}" == "401" ]]; then
  echo "✓ /api/me correctly returned HTTP 401 (Unauthenticated)"
else
  echo "✗ Expected HTTP 401, got HTTP ${CODE}"
  echo "  Response body:"
  sed -n '1,200p' "$TMP_BODY" 2>/dev/null || true
  echo
  exit 1
fi
echo

echo "Test 3: Minting token using php artisan imdc:mint-debug-token..."
set +e
TOKEN_OUTPUT="$(php artisan imdc:mint-debug-token 2>&1)"
TOKEN_EXIT=$?
set -e

# Normalize exit code (never 255)
if [[ $TOKEN_EXIT -ne 0 ]] && [[ $TOKEN_EXIT -ne 1 ]]; then
  TOKEN_EXIT=1
fi

if [[ $TOKEN_EXIT -ne 0 ]]; then
  echo "✗ Token mint failed"
  echo "  Error: $TOKEN_OUTPUT"
  echo
  exit 1
fi

# Parse token from output (support "TOKEN=..." or raw token on last line)
TOKEN="$(echo "$TOKEN_OUTPUT" | tr -d '\r' | awk 'NF{t=$0} END{print t}' | sed -E 's/^TOKEN=//')"
if [[ -z "${TOKEN}" ]]; then
  echo "✗ Token mint returned empty output"
  echo "  Output: $TOKEN_OUTPUT"
  echo
  exit 1
fi
echo "✓ Token minted successfully (${#TOKEN} chars)"
echo

echo "Test 4: Asserting /api/me returns 200 with token..."
CODE="$(curl -sS -o "$TMP_BODY" -w "%{http_code}" \
  --connect-timeout 2 --max-time 10 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  "${API_BASE_URL}/api/me" || echo "000000")"

if [[ "${CODE}" == "200" ]]; then
  echo "✓ /api/me correctly returned HTTP 200 with token"
else
  echo "✗ Got HTTP ${CODE} (should be 200) - token may be invalid or auth miswired"
  echo "  Response body:"
  sed -n '1,200p' "$TMP_BODY" 2>/dev/null || true
  echo
  exit 1
fi
echo

echo "=== Authentication Guardrail PASSED ==="
