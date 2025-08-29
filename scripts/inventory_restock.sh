#!/usr/bin/env bash
set -Eeuo pipefail
BASE=${BASE:-http://localhost}
TOKEN=${TOKEN:-$(php scripts/get_token.php | tr -d '\r\n')}
AUTH=(-H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json")

PRODUCT_ID=${1:?Usage: inventory_restock.sh <product_id> <qty> [reason]}
QTY=${2:?Usage: inventory_restock.sh <product_id> <qty> [reason]}
REASON=${3:-restock}

curl -fsS -X POST "${AUTH[@]}" \
  -d "$(jq -n --argjson qty "$QTY" --arg reason "$REASON" '{qty:qty,reason:reason}')" \
  "$BASE/api/market/products/${PRODUCT_ID}/inventory/add" | jq .
