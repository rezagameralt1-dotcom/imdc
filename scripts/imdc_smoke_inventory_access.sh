#!/usr/bin/env bash
set -euo pipefail
set +H; set +o histexpand

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
PRODUCT_ID="${PRODUCT_ID:-019b6cbe-ceb7-7171-a8ee-7567683f73fd}"

EMAIL="imdc_test_$(date +%s)@example.com"
PASS='Passw0rd!123'
NAME="IMDC Test"

curl -sS -X POST "$BASE_URL/api/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"$NAME\",\"email\":\"$EMAIL\",\"password\":\"$PASS\",\"password_confirmation\":\"$PASS\"}" >/dev/null

curl -sS -X POST "$BASE_URL/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}" \
  | tee /tmp/imdc_login.json >/dev/null

TOKEN="$(php -r '$j=json_decode(file_get_contents("/tmp/imdc_login.json"),true); echo ($j["data"]["token"] ?? $j["token"] ?? "");')"
test "${#TOKEN}" -gt 10 || (echo "LOGIN FAILED"; cat /tmp/imdc_login.json; exit 1)

AUTH="Authorization: Bearer $TOKEN"

echo "TOKEN_LEN=${#TOKEN}"

echo "[GET inventory]"
curl -sS "$BASE_URL/api/v1/inventory/$PRODUCT_ID" -H "$AUTH" | cat
echo

echo "[POST adjust]"
curl -sS -X POST "$BASE_URL/api/v1/inventory/$PRODUCT_ID/adjust" \
  -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"delta": 1, "reason":"test"}' | cat
echo
