#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1090
source "$DIR/env.sh"
# shellcheck disable=SC1090
source "$DIR/token.sh"

json_get() {
  local key="$1"
  php -r '
    $d = json_decode(stream_get_contents(STDIN), true);
    $v = $d;
    foreach (explode(".", $argv[1]) as $k) {
      if (is_array($v) && array_key_exists($k, $v)) { $v = $v[$k]; }
      else { $v = null; break; }
    }
    if (is_bool($v)) echo $v ? "true" : "false";
    elseif ($v === null) echo "";
    else echo $v;
  ' "$key"
}

curl_json() {
  local method="$1"; shift
  local url="$1"; shift
  curl -sS -X "$method" "$url" -H "Content-Type: application/json" "$@"
}

AUTH=(-H "Authorization: Bearer $TOKEN")

echo "== create product (draft) =="
SKU="MKTSMOKE-$(date +%s)-$RANDOM"
P_CREATE="$(curl_json POST "$BASE_URL/api/v1/products" "${AUTH[@]}" \
  -d "{\"sku\":\"$SKU\",\"name\":\"MK Smoke\",\"price\":10,\"currency\":\"USD\",\"status\":\"draft\"}")"
if [[ "$(printf '%s' "$P_CREATE" | json_get success)" == "false" ]]; then
  echo "$P_CREATE"
  exit 1
fi
PRODUCT_ID="$(printf '%s' "$P_CREATE" | json_get data.id)"
echo "PRODUCT_ID=$PRODUCT_ID"

echo "== activate product =="
curl_json PATCH "$BASE_URL/api/v1/products/$PRODUCT_ID" "${AUTH[@]}" -d '{"status":"active"}' >/dev/null || true

echo "== inventory adjust +10 =="
curl_json POST "$BASE_URL/api/v1/inventory/$PRODUCT_ID/adjust" "${AUTH[@]}" -d '{"delta":10,"reason":"smoke"}' >/dev/null

echo "== create order #1 qty=1 =="
O1_CREATE="$(curl_json POST "$BASE_URL/api/v1/orders" "${AUTH[@]}" -d "{\"items\":[{\"product_id\":\"$PRODUCT_ID\",\"quantity\":1}]}")"
if [[ "$(printf '%s' "$O1_CREATE" | json_get success)" == "false" ]]; then
  echo "$O1_CREATE"
  exit 1
fi
ORDER1_ID="$(printf '%s' "$O1_CREATE" | json_get data.id)"
echo "ORDER1_ID=$ORDER1_ID"

echo "== pay order #1 =="
curl_json POST "$BASE_URL/api/v1/orders/$ORDER1_ID/pay" "${AUTH[@]}" -d '{}' >/dev/null || true

echo "== create order #2 qty=1 =="
O2_CREATE="$(curl_json POST "$BASE_URL/api/v1/orders" "${AUTH[@]}" -d "{\"items\":[{\"product_id\":\"$PRODUCT_ID\",\"quantity\":1}]}")"
if [[ "$(printf '%s' "$O2_CREATE" | json_get success)" == "false" ]]; then
  echo "$O2_CREATE"
  exit 1
fi
ORDER2_ID="$(printf '%s' "$O2_CREATE" | json_get data.id)"
echo "ORDER2_ID=$ORDER2_ID"

echo "== cancel order #2 =="
C2="$(curl_json POST "$BASE_URL/api/v1/orders/$ORDER2_ID/cancel" "${AUTH[@]}" -d '{}')"
echo "Cancel response: $C2"
if [[ "$(printf '%s' "$C2" | json_get success)" == "false" ]]; then
  echo "$C2"
  exit 1
fi

echo "== fetch order #2 =="
O2_AFTER="$(curl_json GET "$BASE_URL/api/v1/orders/$ORDER2_ID" "${AUTH[@]}")"
STATUS2="$(printf '%s' "$O2_AFTER" | json_get data.status)"
echo "Order2 status: $STATUS2"
if [[ "$STATUS2" != "canceled" ]]; then
  echo "Expected order2 status=canceled"
  echo "$O2_AFTER"
  exit 1
fi

echo "== fetch inventory =="
INV="$(curl_json GET "$BASE_URL/api/v1/inventory/$PRODUCT_ID" "${AUTH[@]}")"
echo "$INV"

AVA="$(printf '%s' "$INV" | json_get data.available_quantity)"
RES="$(printf '%s' "$INV" | json_get data.reserved_quantity)"
if [[ "$RES" != "0" || "$AVA" != "9" ]]; then
  echo "Inventory assertion failed (expected avail=9,res=0)"
  exit 1
fi

echo "== RESULT SUMMARY =="
echo "PRODUCT_ID=$PRODUCT_ID"
echo "ORDER1_ID=$ORDER1_ID"
echo "ORDER2_ID=$ORDER2_ID"
