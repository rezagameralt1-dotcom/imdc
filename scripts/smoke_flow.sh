#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/smoke/.env"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
fi

rand_email() {
  printf "imdc-smoke-%s-%s@example.com" "$(date +%s)" "$RANDOM"
}

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
EMAIL="${EMAIL:-$(rand_email)}"
PASSWORD="${PASSWORD:-Passw0rd!123}"

fail() {
  echo "ERROR: $*" >&2
  exit 1
}

json_get() {
  local key="$1"
  php -r '
    $d=json_decode(stream_get_contents(STDIN),true);
    $v=$d;
    foreach(explode(".",$argv[1]) as $k){if(is_array($v)&&array_key_exists($k,$v)){$v=$v[$k];}else{$v=null;break;}}
    if(is_bool($v)) echo $v?"true":"false"; elseif($v===null) echo ""; else echo $v;
  ' "$key"
}

curl_json() {
  local method="$1"; shift
  local url="$1"; shift
  curl -sS -X "$method" "$url" -H 'Content-Type: application/json' "$@"
}

printf "Registering user %s...\n" "$EMAIL"
REG_PAYLOAD="{\"name\":\"Smoke User\",\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}"
curl_json POST "$BASE_URL/api/v1/auth/register" -d "$REG_PAYLOAD" >/dev/null || true

printf "Logging in...\n"
LOGIN_RESP=$(curl_json POST "$BASE_URL/api/v1/auth/login" -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
TOKEN=$(printf '%s' "$LOGIN_RESP" | json_get data.token)
[[ -n "$TOKEN" ]] || fail "Token missing"
AUTH=(-H "Authorization: Bearer $TOKEN")

printf "Creating product...\n"
SKU="SMOKE-$(date +%s)-$RANDOM"
PROD_RESP=$(curl_json POST "$BASE_URL/api/v1/products" "${AUTH[@]}" -d "{\"sku\":\"$SKU\",\"name\":\"Smoke Product\",\"price\":10,\"currency\":\"USD\",\"status\":\"draft\"}")
PRODUCT_ID=$(printf '%s' "$PROD_RESP" | json_get data.id)
[[ -n "$PRODUCT_ID" ]] || fail "Product ID missing"

printf "Activating product...\n"
curl_json PATCH "$BASE_URL/api/v1/products/$PRODUCT_ID" "${AUTH[@]}" -d '{"status":"active"}' >/dev/null || fail "Activate failed"

printf "Adjusting inventory...\n"
curl_json POST "$BASE_URL/api/v1/inventory/$PRODUCT_ID/adjust" "${AUTH[@]}" -d '{"delta":10,"reason":"smoke"}' >/dev/null || fail "Inventory adjust failed"

printf "Creating customer (register call already creates account)...\n"
ME_RESP=$(curl_json GET "$BASE_URL/api/v1/auth/me" "${AUTH[@]}")
CUSTOMER_ID=$(printf '%s' "$ME_RESP" | json_get data.id)
[[ -n "$CUSTOMER_ID" ]] || fail "Customer id missing"

printf "Creating order...\n"
ORDER_RESP=$(curl_json POST "$BASE_URL/api/v1/orders" "${AUTH[@]}" -d "{\"items\":[{\"product_id\":\"$PRODUCT_ID\",\"quantity\":1}],\"currency\":\"USD\"}")
ORDER_ID=$(printf '%s' "$ORDER_RESP" | json_get data.id)
[[ -n "$ORDER_ID" ]] || fail "Order id missing"

printf "Paying order...\n"
curl_json POST "$BASE_URL/api/v1/orders/$ORDER_ID/pay" "${AUTH[@]}" -d '{}' >/dev/null || fail "Pay failed"

INV_FINAL=$(curl_json GET "$BASE_URL/api/v1/inventory/$PRODUCT_ID" "${AUTH[@]}")
AVA_FINAL=$(printf '%s' "$INV_FINAL" | json_get data.available_quantity)
RES_FINAL=$(printf '%s' "$INV_FINAL" | json_get data.reserved_quantity)

printf "--- SMOKE SUMMARY ---\n"
printf "PRODUCT_ID=%s\n" "$PRODUCT_ID"
printf "ORDER_ID=%s\n" "$ORDER_ID"
printf "AVAILABLE=%s\n" "${AVA_FINAL:-unknown}"
printf "RESERVED=%s\n" "${RES_FINAL:-unknown}"
