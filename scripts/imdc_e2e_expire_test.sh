#!/bin/bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
PRODUCT_ID="${PRODUCT_ID:-019b6cbe-ceb7-7171-a8ee-7567683f73fd}"
TTL_MINUTES="${TTL_MINUTES:-1}"
TRACE(){ echo "[IMDC-E2E] $*"; }
FAIL(){ echo "[IMDC-E2E][ERROR] $*" >&2; exit 1; }

json_get(){
  local key="$1"
  php -r '
    $d=json_decode(stream_get_contents(STDIN),true);
    $v=$d;
    foreach(explode(".",$argv[1]) as $k){if(is_array($v)&&array_key_exists($k,$v)){$v=$v[$k];}else{$v=null;break;}}
    if(is_bool($v)) echo $v?"true":"false"; elseif($v===null) echo ""; else echo $v;
  ' "$key"
}

rand_email(){ echo "imdc-smoke-$(date +%s)-$RANDOM@example.com"; }

EMAIL=$(rand_email)
PASSWORD="Password123!"

TRACE "Register user $EMAIL"
REG_PAYLOAD="{\"name\":\"Smoke User\",\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}"
REG_RESP=$(curl -sS -X POST "$BASE_URL/api/v1/auth/register" -H 'Content-Type: application/json' -d "$REG_PAYLOAD")

TRACE "Login user"
LOGIN_RESP=$(curl -sS -X POST "$BASE_URL/api/v1/auth/login" -H 'Content-Type: application/json' -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
TOKEN=$(printf '%s' "$LOGIN_RESP" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["token"] ?? $d["token"] ?? $d["data"]["access_token"] ?? $d["access_token"] ?? "";')
[[ -n "$TOKEN" ]] || { echo "$LOGIN_RESP"; FAIL "Token missing"; }
TRACE "Token len=${#TOKEN}"
AUTH=(-H "Authorization: Bearer $TOKEN")

TRACE "auth/me"
ME_RESP=$(curl -sS -X GET "$BASE_URL/api/v1/auth/me" "${AUTH[@]}")
TRACE "Inventory check"
INV_CHECK=$(curl -sS -X GET "$BASE_URL/api/v1/inventory/$PRODUCT_ID" "${AUTH[@]}")

ORDER_PAYLOAD="{\"currency\":\"USD\",\"items\":[{\"product_id\":\"$PRODUCT_ID\",\"quantity\":1}]}"
TRACE "Create order"
O_CREATE=$(curl -sS -X POST "$BASE_URL/api/v1/orders" "${AUTH[@]}" -H 'Content-Type: application/json' -d "$ORDER_PAYLOAD")
printf '%s' "$O_CREATE" > /tmp/imdc_order_create.json
ORDER_ID=$(printf '%s' "$O_CREATE" | json_get data.id)
[[ -n "$ORDER_ID" ]] || { cat /tmp/imdc_order_create.json; FAIL "ORDER_ID missing"; }
TRACE "ORDER_ID=$ORDER_ID"

TRACE "Reserve inventory explicitly"
RES_PAYLOAD="{\"order_id\":\"$ORDER_ID\",\"product_id\":\"$PRODUCT_ID\",\"qty\":1,\"shop_customer_id\":\"script\"}"
RES_RESP=$(curl -sS -X POST "$BASE_URL/api/v1/inventory/reserve" "${AUTH[@]}" -H 'Content-Type: application/json' -d "$RES_PAYLOAD")
printf '%s' "$RES_RESP" > /tmp/imdc_inventory_reserve.json

TRACE "Patch order timestamps for fast expiry"
php artisan tinker --execute "\\DB::connection('orders')->table('orders')->where('id','$ORDER_ID')->update(['status'=>'pending','created_at'=>now()->subMinutes(5),'updated_at'=>now()->subMinutes(5)]);" >/dev/null

TRACE "Run expiry command"
IMDC_ORDER_RESERVATION_TTL_MINUTES=$TTL_MINUTES php artisan imdc:expire-order-reservations --minutes=$TTL_MINUTES --limit=50

TRACE "Check reservations rows"
php artisan tinker --execute "echo json_encode(\\DB::connection('inventory')->table('inventory_reservations')->where('order_id','$ORDER_ID')->get(), JSON_PRETTY_PRINT);" | tee /tmp/imdc_inventory_reservations_after.json

if [[ -f storage/logs/imdc-expire-order-reservations.log ]]; then
  TRACE "Tail scheduler log"
  tail -n 50 storage/logs/imdc-expire-order-reservations.log || true
fi

TRACE "Fetch order after expiry"
ORDER_AFTER=$(curl -sS -X GET "$BASE_URL/api/v1/orders/$ORDER_ID" "${AUTH[@]}")
ORDER_STATUS=$(printf '%s' "$ORDER_AFTER" | json_get data.status)
TRACE "ORDER_STATUS_AFTER=$ORDER_STATUS"

TRACE "Fetch inventory after expiry"
FINAL_INV=$(curl -sS -X GET "$BASE_URL/api/v1/inventory/$PRODUCT_ID" "${AUTH[@]}")
TRACE "FINAL_INV: $FINAL_INV"

TRACE "Summary"
echo "ORDER_ID=$ORDER_ID"
echo "TOKEN_LEN=${#TOKEN}"
echo "TTL_MINUTES=$TTL_MINUTES"
echo "ORDER_STATUS_AFTER=$ORDER_STATUS"