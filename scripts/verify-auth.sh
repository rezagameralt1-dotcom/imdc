#!/usr/bin/env bash
set -euo pipefail

echo "=== Authentication Verification (Official Guardrail) ==="
echo

# Detect container context (best-effort)
IN_CONTAINER="0"
if [[ -f "/.dockerenv" ]]; then IN_CONTAINER="1"; fi
if [[ "${IN_CONTAINER}" == "0" ]]; then
  if ! command -v docker >/dev/null 2>&1; then IN_CONTAINER="1"; fi
fi

API_BASE_URL="http://127.0.0.1:8080"
EXEC_CTX="Host (direct PHP)"
if [[ "${IN_CONTAINER}" == "1" ]]; then
  API_BASE_URL="http://web"
  EXEC_CTX="Container (docker network)"
fi

echo "API Base URL: ${API_BASE_URL}"
echo "Execution context: ${EXEC_CTX}"
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
TOKEN="$(php artisan imdc:mint-debug-token 2>/dev/null | tr -d '\r' | awk 'NF{t=$0} END{print t}')"
if [[ -z "${TOKEN}" ]]; then
  echo "✗ Token mint returned empty output"
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
