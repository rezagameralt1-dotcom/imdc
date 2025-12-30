#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"

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
 else{$v=null;break;}
}
echo ($v===null?"":$v);
' "$1"
}

curl_json(){
 local m="$1"; shift
 local u="$1"; shift
 curl -sS -X "$m" "$u" -H "Content-Type: application/json" "$@"
}

echo "Register..."
curl_json POST "$BASE_URL/api/v1/auth/register" \
 -d "{\"name\":\"Smoke User\",\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"password_confirmation\":\"$PASSWORD\"}" >/dev/null

echo "Login..."
LOGIN="$(curl_json POST "$BASE_URL/api/v1/auth/login" \
 -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")"

TOKEN="$(printf '%s' "$LOGIN" | json_get data.token)"
[[ -n "$TOKEN" ]] || fail "Token missing"
AUTH=(-H "Authorization: Bearer $TOKEN")

SKU="SMOKE-$(date +%s)-$RANDOM"

echo "Create product..."
P="$(curl_json POST "$BASE_URL/api/v1/products" "${AUTH[@]}" \
 -d "{\"sku\":\"$SKU\",\"name\":\"Smoke Prod\",\"price\":10,\"currency\":\"USD\",\"status\":\"draft\"}")"

PRODUCT_ID="$(printf '%s' "$P" | json_get data.id)"
[[ -n "$PRODUCT_ID" ]] || { echo "$P"; fail "Product ID missing"; }

curl_json PATCH "$BASE_URL/api/v1/products/$PRODUCT_ID" "${AUTH[@]}" \
 -d '{"status":"active"}' >/dev/null

curl_json POST "$BASE_URL/api/v1/inventory/$PRODUCT_ID/adjust" "${AUTH[@]}" \
 -d '{"delta":10,"reason":"smoke"}' >/dev/null

echo "Create order..."
O="$(curl_json POST "$BASE_URL/api/v1/orders" "${AUTH[@]}" \
 -d "{\"items\":[{\"product_id\":\"$PRODUCT_ID\",\"quantity\":1}]}")"

ORDER_ID="$(printf '%s' "$O" | json_get data.id)"
[[ -n "$ORDER_ID" ]] || { echo "$O"; fail "Order ID missing"; }

curl_json POST "$BASE_URL/api/v1/orders/$ORDER_ID/pay" "${AUTH[@]}" -d '{}' >/dev/null

echo "SMOKE OK"
