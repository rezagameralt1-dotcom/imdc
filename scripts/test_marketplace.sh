#!/usr/bin/env bash
set -euo pipefail

BASE="${BASE:-http://localhost}"
EMAIL="${EMAIL:-admin@example.com}"
PASS="${PASS:-password}"

echo "[1] گرفتن توکن..."
TOKEN=$(curl -s -X POST "$BASE/api/auth/token" -H 'Content-Type: application/json' \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}" | jq -r '.data.token')

auth() { echo "Authorization: Bearer $TOKEN"; }

echo "[2] لیست محصولات (0..)"
curl -s "$BASE/api/market/products" -H "$(auth)" | jq '.data.data | length, .[0]'

echo "[3] ایجاد محصول"
PID=$(curl -s -X POST "$BASE/api/market/products" -H 'Content-Type: application/json' -H "$(auth)" \
  -d '{"sku":"SKU-TEST","title":"محصول تست","price":99000,"currency":"IRR"}' | jq -r '.data.id')

echo "=> Product ID: $PID"

echo "[4] افزودن موجودی"
curl -s -X POST "$BASE/api/market/products/$PID/inventory/add" -H 'Content-Type: application/json' -H "$(auth)" \
  -d '{"qty":10,"reason":"initial"}' | jq '.data'

echo "[5] ساخت سفارش"
OID=$(curl -s -X POST "$BASE/api/market/orders" -H 'Content-Type: application/json' -H "$(auth)" \
  -d "{\"items\":[{\"product_id\":$PID,\"qty\":2}]}" | jq -r '.data.id')
echo "=> Order ID: $OID"

echo "[6] پرداخت سفارش"
curl -s -X POST "$BASE/api/market/orders/$OID/pay" -H "$(auth)" | jq '.data.status, .data.total_amount'

echo "[7] موجودی پس از فروش"
curl -s "$BASE/api/market/products/$PID/inventory" -H "$(auth)" | jq '.data.stock_on_hand, .data.stock_reserved, .data.stock_available'
