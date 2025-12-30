#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/smoke/.env"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
fi

: "${BASE_URL:=http://127.0.0.1:8000}"
: "${API_BASE:=${BASE_URL}}"

rand_email() { printf "imdc-smoke-%s-%s@example.com" "$(date +%s)" "$RANDOM"; }

if [[ -z "${EMAIL:-}" ]]; then EMAIL="$(rand_email)"; fi
if [[ -z "${PASSWORD:-}" ]]; then PASSWORD="Passw0rd!123"; fi

curl_call() {
  local method="$1"; shift
  local url="$1"; shift
  curl -sS -X "$method" "$url" -H "Content-Type: application/json" "$@"
}

json_get() {
  local key="$1"
  php -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    $path = explode(".", $argv[1]);
    $v = $data;
    foreach ($path as $k) {
        if (is_array($v) && array_key_exists($k, $v)) {
            $v = $v[$k];
        } else {
            $v = "";
            break;
        }
    }
    if (is_bool($v)) { echo $v ? "true" : "false"; }
    else { echo $v; }
  ' "$key"
}

ensure_non_empty() {
  local value="$1" name="$2"
  if [[ -z "$value" ]]; then
    echo "ERROR: $name is empty" >&2
    exit 1
  fi
}

ensure_non_empty "$EMAIL" "EMAIL"
ensure_non_empty "$PASSWORD" "PASSWORD"

echo "Login..."
LOGIN_RESP="$(curl_call POST "$API_BASE/api/v1/auth/login" -d '{"email":"'"$EMAIL"'","password":"'"$PASSWORD"'"}')"
TOKEN="$(printf '%s' "$LOGIN_RESP" | json_get "data.token")"
ensure_non_empty "$TOKEN" "TOKEN"

AUTH_HEADER=(-H "Authorization: Bearer $TOKEN")
SKU="SMOKE-$(date +%s)-$RANDOM"

echo "Create product..."
PRODUCT_RESP="$(curl_call POST "$API_BASE/api/v1/products" "${AUTH_HEADER[@]}" -d '{"sku":"'"$SKU"'","name":"Smoke Test","price":10,"currency":"USD","status":"draft"}')"
PRODUCT_ID="$(printf '%s' "$PRODUCT_RESP" | json_get "data.id")"
ensure_non_empty "$PRODUCT_ID" "PRODUCT_ID"

echo "Activate product..."
curl_call PATCH "$API_BASE/api/v1/products/$PRODUCT_ID" "${AUTH_HEADER[@]}" -d '{"status":"active"}' >/dev/null

echo "Adjust inventory +10..."
curl_call POST "$API_BASE/api/v1/inventory/$PRODUCT_ID/adjust" "${AUTH_HEADER[@]}" -d '{"delta":10,"reason":"smoke"}' >/dev/null

echo "Create order qty=2..."
ORDER_RESP="$(curl_call POST "$API_BASE/api/v1/orders" "${AUTH_HEADER[@]}" -d '{"items":[{"product_id":"'"$PRODUCT_ID"'","quantity":2}]}')"
ORDER_ID="$(printf '%s' "$ORDER_RESP" | json_get "data.id")"
ORDER_INV_STATUS="$(printf '%s' "$ORDER_RESP" | json_get "data.inventory_status")"
ensure_non_empty "$ORDER_ID" "ORDER_ID"

if [[ "$ORDER_INV_STATUS" == "pending" || -z "$ORDER_INV_STATUS" ]]; then
  echo "Reserve inventory explicitly..."
  curl_call POST "$API_BASE/api/v1/inventory/reserve" "${AUTH_HEADER[@]}" -d '{"order_id":"'"$ORDER_ID"'","product_id":"'"$PRODUCT_ID"'","qty":2}' >/dev/null
fi

echo "Pay order..."
curl_call POST "$API_BASE/api/v1/orders/$ORDER_ID/pay" "${AUTH_HEADER[@]}" -d '{}' >/dev/null

echo "Fetch final inventory..."
INV_FINAL="$(curl_call GET "$API_BASE/api/v1/inventory/$PRODUCT_ID" "${AUTH_HEADER[@]}")"
INV_AVAILABLE="$(printf '%s' "$INV_FINAL" | json_get "data.available_quantity")"
INV_RESERVED="$(printf '%s' "$INV_FINAL" | json_get "data.reserved_quantity")"

echo "Fetch final order..."
ORDER_FINAL="$(curl_call GET "$API_BASE/api/v1/orders/$ORDER_ID" "${AUTH_HEADER[@]}")"
FINAL_STATUS="$(printf '%s' "$ORDER_FINAL" | json_get "data.status")"
FINAL_INV_STATUS="$(printf '%s' "$ORDER_FINAL" | json_get "data.inventory_status")"

echo "----- SMOKE SUMMARY -----"
echo "PRODUCT_ID: $PRODUCT_ID"
echo "ORDER_ID: $ORDER_ID"
echo "Inventory available: ${INV_AVAILABLE:-unknown}"
echo "Inventory reserved: ${INV_RESERVED:-unknown}"
echo "Order status: ${FINAL_STATUS:-unknown}"
echo "Order inventory_status: ${FINAL_INV_STATUS:-unknown}"


