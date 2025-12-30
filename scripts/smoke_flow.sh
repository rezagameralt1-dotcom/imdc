#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/smoke/.env"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  source "$ENV_FILE"
  set +a
fi

: "${BASE_URL:=http://127.0.0.1:8000}"
: "${API_BASE:=${BASE_URL}}"

rand_email() { printf "imdc-smoke-%s-%s@example.com" "$(date +%s)" "$RANDOM"; }

EMAIL="${EMAIL:-$(rand_email)}"
PASSWORD="${PASSWORD:-Passw0rd!123}"

fail(){ echo "ERROR: $*" >&2; exit 1; }

json_get() {
  php -r '
  $d=json_decode(stream_get_contents(STDIN),true);
  $v=$d;
  foreach(explode(".",$argv[1]) as $k){
    if(is_array($v)&&array_key_exists($k,$v)) $v=$v[$k];
    else {$v=null; break;}
  }
  echo ($v===null?"":$v);
  ' "$1"
}

curl_json() {
  local method="$1"; shift
  local url="$1"; shift
  curl -sS -X "$method" "$url" -H "Content-Type: application/json" "$@"
}

echo "Register..."
curl_json POST "$API_BASE/api/v1/auth/register" \
  -d "{\"name\":\"Smoke User\",\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"password_confirmation\":\"$PASSWORD\"}" >/dev/null

echo "Login..."
LOGIN_RESP="$(curl_json POST "$API_BASE/api/v1/auth/login" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")"

TOKEN="$(printf '%s' "$LOGIN_RESP" | json_get data.token)"
[[ -n "$TOKEN" ]] || fail "Token missing"
AUTH=(-H "Authorization: Bearer $TOKEN")

SKU="SMOKE-$(date +%s)-$RANDOM"

echo "Create product..."
P_RESP="$(curl_json POST "$API_BASE/api/v1/products" "${AUTH[@]}" \
  -d "{\"sku\":\"$SKU\",\"name\":\"Smoke Prod\",\"price\":10,\"currency\":\"USD\",\"status\":\"draft\"}")"

PRODUCT_ID="$(printf '%s' "$P_RESP" | json_get data.id)"
[[ -n "$PRODUCT_ID" ]] || fail "Product ID missing"

curl_json PATCH "$API_BASE/api/v1/products/$PRODUCT_ID" "${AUTH[@]}" \
  -d '{"status":"active"}' >/dev/null

curl_json POST "$API_BASE/api/v1/inventory/$PRODUCT_ID/adjust" "${AUTH[@]}" \
  -d '{"delta":10,"reason":"smoke"}' >/dev/null

echo "Create order..."
O_RESP="$(curl_json POST "$API_BASE/api/v1/orders" "${AUTH[@]}" \
  -d "{\"items\":[{\"product_id\":\"$PRODUCT_ID\",\"quantity\":1}]}")"

ORDER_ID="$(printf '%s' "$O_RESP" | json_get data.id)"
[[ -n "$ORDER_ID" ]] || fail "Order ID missing"

curl_json POST "$API_BASE/api/v1/orders/$ORDER_ID/pay" "${AUTH[@]}" -d '{}' >/dev/null

echo "SMOKE OK"
