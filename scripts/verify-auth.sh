#!/bin/bash
# Authentication Verification Script (Official Guardrail)
# Verifies web reachability and authentication endpoints work correctly

set -euo pipefail

cd "$(dirname "$0")/.." || exit 1

API_PORT="${IMDC_API_PORT:-8080}"
API_HOST="${IMDC_API_HOST:-127.0.0.1}"
BASE_URL="http://${API_HOST}:${API_PORT}"

echo "=== Authentication Verification (Official Guardrail) ==="
echo ""
echo "API Base URL: ${BASE_URL}"
echo ""

FAILED=0

# Test 1: Assert web is reachable on http://127.0.0.1:8080/
echo "Test 1: Asserting web is reachable on ${BASE_URL}/..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/" 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "000" ]; then
    echo "✗ Cannot connect to ${BASE_URL}/ (web service may not be running)"
    echo "  Start services with: docker compose up -d web app"
    FAILED=1
elif [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 500 ]; then
    echo "✓ Web is reachable (HTTP ${HTTP_CODE})"
else
    echo "✗ Web returned unexpected HTTP ${HTTP_CODE}"
    FAILED=1
fi

# Test 2: Assert /api/me returns 401 without token
echo ""
echo "Test 2: Asserting /api/me returns 401 without token..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Accept: application/json" \
    "${BASE_URL}/api/me" 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "000" ]; then
    echo "✗ Cannot connect to ${BASE_URL}/api/me"
    FAILED=1
elif [ "$HTTP_CODE" = "500" ]; then
    echo "✗ Got HTTP 500 (should be 401) - likely DB connection or exception handling issue"
    echo "  Response body:"
    curl -s -H "Accept: application/json" "${BASE_URL}/api/me" 2>/dev/null | head -10
    FAILED=1
elif [ "$HTTP_CODE" = "401" ]; then
    echo "✓ /api/me correctly returned HTTP 401 (Unauthenticated)"
else
    echo "✗ Expected HTTP 401, got HTTP ${HTTP_CODE}"
    FAILED=1
fi

# Test 3: Mint a token using imdc:mint-debug-token
echo ""
echo "Test 3: Minting token using php artisan imdc:mint-debug-token..."
TOKEN=$(php artisan imdc:mint-debug-token 2>/dev/null | tr -d '\r\n' || echo "")

if [ -z "$TOKEN" ]; then
    echo "✗ Failed to mint token (command returned empty)"
    echo "  Ensure at least one user exists in database"
    FAILED=1
else
    echo "✓ Token minted successfully (${#TOKEN} chars)"
fi

# Test 4: Assert /api/me returns 200 with token
if [ -n "$TOKEN" ] && [ $FAILED -eq 0 ]; then
    echo ""
    echo "Test 4: Asserting /api/me returns 200 with token..."
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "Accept: application/json" \
        "${BASE_URL}/api/me" 2>/dev/null || echo "000")
    
    if [ "$HTTP_CODE" = "000" ]; then
        echo "✗ Cannot connect to ${BASE_URL}/api/me"
        FAILED=1
    elif [ "$HTTP_CODE" = "200" ]; then
        echo "✓ /api/me correctly returned HTTP 200 (Authenticated)"
        # Verify response structure
        RESPONSE=$(curl -s -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/json" "${BASE_URL}/api/me" 2>/dev/null || echo "")
        if echo "$RESPONSE" | grep -q '"success".*true' && echo "$RESPONSE" | grep -q '"data"' && echo "$RESPONSE" | grep -q '"trace_id"'; then
            echo "  ✓ Response has standard envelope with user data"
        else
            echo "  ⚠ Response may not have expected structure"
        fi
    elif [ "$HTTP_CODE" = "401" ]; then
        echo "✗ Got HTTP 401 (should be 200) - token may be invalid"
        FAILED=1
    else
        echo "✗ Expected HTTP 200, got HTTP ${HTTP_CODE}"
        echo "  Response body:"
        curl -s -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/json" "${BASE_URL}/api/me" 2>/dev/null | head -10
        FAILED=1
    fi
else
    echo ""
    echo "Test 4: Skipping (token generation failed in Test 3)"
fi

echo ""
if [ $FAILED -eq 0 ]; then
    echo "=== All Authentication Tests Passed ==="
    exit 0
else
    echo "=== Some Authentication Tests Failed ==="
    exit 1
fi
