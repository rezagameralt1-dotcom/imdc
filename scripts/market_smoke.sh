#!/usr/bin/env bash
set -Eeuo pipefail

BASE=${BASE:-http://localhost}
TOKEN=${TOKEN:-$(php scripts/get_token.php | tr -d '\r\n')}
PRODUCT_ID=${PRODUCT_ID:-$(sudo -u postgres psql -At -d imdc -c "select id from products order by id desc limit 1;")}
UNIT_PRICE=${UNIT_PRICE:-99000}
QTY=${QTY:-1}
AUTH=(-H "Accept: application/json" -H "Authorization: Bearer $TOKEN")

echo "[PING]"
curl -fsS "${AUTH[@]}" "$BASE/api/secure/ping" | jq .

echo "[GET] inventory"
INV_BEFORE=$(curl -fsS "${AUTH[@]}" "$BASE/api/market/products/${PRODUCT_ID}/inventory")
echo "$INV_BEFORE" | jq .

echo "[POST] order"
ORDER_JSON=$(curl -fsS -X POST "${AUTH[@]}" -H "Content-Type: application/json" \
  -d "{\"items\":[{\"product_id\":${PRODUCT_ID},\"qty\":${QTY},\"unit_price\":${UNIT_PRICE}}]}" \
  "$BASE/api/market/orders")
echo "$ORDER_JSON" | jq .
ORDER_ID=$(echo "$ORDER_JSON" | jq -r '.data.id')

echo "[POST] pay #$ORDER_ID"
curl -fsS -X POST "${AUTH[@]}" "$BASE/api/market/orders/${ORDER_ID}/pay" | jq .

echo "[GET] inventory (after)"
INV_AFTER=$(curl -fsS "${AUTH[@]}" "$BASE/api/market/products/${PRODUCT_ID}/inventory")
echo "$INV_AFTER" | jq .

echo "[GET] movements (last)"
LAST_JSON=$(curl -fsS "${AUTH[@]}" "$BASE/api/market/products/${PRODUCT_ID}/inventory/movements")
echo "$LAST_JSON" | jq '.data.data[0]'
LAST_REASON=$(echo "$LAST_JSON" | jq -r '.data.data[0].reason')
test "sale:order#${ORDER_ID}" = "$LAST_REASON" && echo "OK ✅ movement matched" || { echo "Mismatch ❌"; exit 1; }
