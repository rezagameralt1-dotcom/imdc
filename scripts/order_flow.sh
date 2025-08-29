#!/usr/bin/env bash
set -Eeuo pipefail

BASE=${BASE:-http://localhost}
TOKEN=${TOKEN:?TOKEN is empty}
PRODUCT_ID=${PRODUCT_ID:?PRODUCT_ID is empty}

echo "[GET] /api/market/orders"
curl -sS -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  "$BASE/api/market/orders" | jq .

echo "[POST] /api/market/orders"
ORDER_RES=$(
  curl -sS -X POST \
    -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"items\":[{\"product_id\":${PRODUCT_ID},\"qty\":2,\"unit_price\":99000}]}" \
    "$BASE/api/market/orders"
)
echo "$ORDER_RES" | jq .

ORDER_ID=$(echo "$ORDER_RES" | jq -r '.data.id // .id // empty')
echo "ORDER_ID=${ORDER_ID}"

if [[ -n "$ORDER_ID" ]]; then
  echo "[POST] /api/market/orders/${ORDER_ID}/pay"
  curl -sS -X POST -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
    "$BASE/api/market/orders/${ORDER_ID}/pay" | jq .

  echo "[GET] /api/market/products/${PRODUCT_ID}/inventory"
  curl -sS -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
    "$BASE/api/market/products/${PRODUCT_ID}/inventory" | jq .

  echo "[GET] /api/market/products/${PRODUCT_ID}/inventory/movements"
  curl -sS -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
    "$BASE/api/market/products/${PRODUCT_ID}/inventory/movements" | jq .
fi
