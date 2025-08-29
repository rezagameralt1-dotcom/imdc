#!/usr/bin/env bash
set -Eeuo pipefail

BASE=${BASE:-http://localhost}
TOKEN=${TOKEN:-$(php scripts/get_token.php | tr -d '\r\n')}
PRODUCT_ID=${PRODUCT_ID:-$(sudo -u postgres psql -At -d imdc -c "select id from products order by id desc limit 1;")}
UNIT_PRICE=${UNIT_PRICE:-99000}
QTY=${QTY:-1}
AUTH=(-H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json")

echo "[PING]"
curl -fsS -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" "$BASE/api/secure/ping" | jq .

echo "[GET] inventory"
INV_BEFORE_JSON=$(curl -fsS -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" "$BASE/api/market/products/${PRODUCT_ID}/inventory")
echo "$INV_BEFORE_JSON" | jq .
BEFORE=$(echo "$INV_BEFORE_JSON" | jq -r '.data.stock_on_hand')

echo "[POST] order"
ORDER_JSON=$(curl -fsS -X POST "${AUTH[@]}" \
  -d "{\"items\":[{\"product_id\":${PRODUCT_ID},\"qty\":${QTY},\"unit_price\":${UNIT_PRICE}}]}" \
  "$BASE/api/market/orders")
echo "$ORDER_JSON" | jq .
ORDER_ID=$(echo "$ORDER_JSON" | jq -r '.data.id')

echo "[POST] pay #$ORDER_ID]"
curl -fsS -X POST -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  "$BASE/api/market/orders/${ORDER_ID}/pay" | jq .

echo "[GET] inventory (after)"
INV_AFTER_JSON=$(curl -fsS -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" "$BASE/api/market/products/${PRODUCT_ID}/inventory")
echo "$INV_AFTER_JSON" | jq .
AFTER=$(echo "$INV_AFTER_JSON" | jq -r '.data.stock_on_hand')

DIFF=$(( BEFORE - AFTER ))
if [ "$DIFF" -ne "$QTY" ]; then
  echo "❌ stock delta mismatch: before=$BEFORE after=$AFTER expected_delta=$QTY got=$DIFF"
  exit 1
fi

echo "[GET] movements (search for sale:order#$ORDER_ID)"
MOV_JSON=$(curl -fsS -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" "$BASE/api/market/products/${PRODUCT_ID}/inventory/movements")
echo "$MOV_JSON" | jq '.data.data[0]'
echo "$MOV_JSON" | jq -e --arg rid "sale:order#$ORDER_ID" --argjson q "$QTY" \
  '.data.data | map(select(.reason==$rid and .type=="OUT" and .quantity==$q)) | length > 0' >/dev/null

echo "OK ✅ movement matched & stock changed by $QTY (from $BEFORE to $AFTER)"
