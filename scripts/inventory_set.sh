#!/usr/bin/env bash
set -Eeuo pipefail
BASE=${BASE:-http://localhost}
TOKEN=${TOKEN:-$(php scripts/get_token.php | tr -d '\r\n')}
AUTH=(-H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json")

PRODUCT_ID=${1:?Usage: inventory_set.sh <product_id> <target_on_hand>}
TARGET=${2:?Usage: inventory_set.sh <product_id> <target_on_hand>}

CUR=$(curl -fsS -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  "$BASE/api/market/products/${PRODUCT_ID}/inventory" | jq -r '.data.stock_on_hand')
DELTA=$(( TARGET - CUR ))
echo "current=$CUR target=$TARGET delta=$DELTA"

if [ "$DELTA" -gt 0 ]; then
  curl -fsS -X POST "${AUTH[@]}" \
    -d "$(jq -n --argjson qty "$DELTA" '{qty:qty,reason:"restock"}')" \
    "$BASE/api/market/products/${PRODUCT_ID}/inventory/add" | jq .
elif [ "$DELTA" -lt 0 ]; then
  curl -fsS -X POST "${AUTH[@]}" \
    -d "$(jq -n --argjson d "$DELTA" '{delta:d,reason:"adjust to target"}')" \
    "$BASE/api/market/products/${PRODUCT_ID}/inventory/adjust" | jq .
else
  echo "already at target"
fi
