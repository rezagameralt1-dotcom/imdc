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
: "${CUSTOMER_ID_OVERRIDE:=${CUSTOMER_ID_OVERRIDE:-}}"

rand_email() { printf "imdc-smoke-%s-%s@example.com" "$(date +%s)" "$RANDOM"; }

if [[ -z "${EMAIL:-}" ]]; then EMAIL="$(rand_email)"; fi
if [[ -z "${PASSWORD:-}" ]]; then PASSWORD="Passw0rd!123"; fi

fail() { echo "ERROR: $*" >&2; FAILED=1; }

json_get() {
  local key="$1"
  php -r '
    $d = json_decode(stream_get_contents(STDIN), true);
    $v = $d;
    foreach (explode(".", $argv[1]) as $k) {
        if (is_array($v) && array_key_exists($k, $v)) $v = $v[$k];
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

ensure_var() {
  local val="$1" name="$2"
  if [[ -z "$val" ]]; then fail "$name is required"; fi
}

FAILED=0
ensure_var "$EMAIL" "EMAIL"
ensure_var "$PASSWORD" "PASSWORD"

echo "Register..."
curl -sS -X POST "/api/v1/auth/register" -H "Content-Type: application/json" -d "{\"name\":\"Smoke User\",\"email\":\"\",\"password\":\"\",\"password_confirmation\":\"\"}" >/dev/null

echo "Login..."
"
LOGIN_RESP="$(curl_json POST "$API_BASE/api/v1/auth/login" -d '{"email":"'"$EMAIL"'","password":"'"$PASSWORD"'"}')"
TOKEN="$(printf '%s' "$LOGIN_RESP" | json_get "data.token")"
if [[ -z "$TOKEN" ]]; then fail "Token missing"; fi
AUTH=(-H "Authorization: Bearer $TOKEN")

SKU="SMOKE-$(date +%s)-$RANDOM"
echo "Create product..."
P_RESP="$(curl_json POST "$API_BASE/api/v1/products" "${AUTH[@]}" -d '{"sku":"'"$SKU"'","name":"Smoke Prod","price":10,"currency":"USD","status":"draft"}')"
PRODUCT_ID="$(printf '%s' "$P_RESP" | json_get "data.id")"
if [[ -z "$PRODUCT_ID" ]]; then fail "Product ID missing"; fi

echo "Activate product..."
curl_json PATCH "$API_BASE/api/v1/products/$PRODUCT_ID" "${AUTH[@]}" -d '{"status":"active"}' >/dev/null || fail "Activate failed"

echo "Adjust inventory +10..."
A_RESP="$(curl_json POST "$API_BASE/api/v1/inventory/$PRODUCT_ID/adjust" "${AUTH[@]}" -d '{"delta":10,"reason":"smoke"}')" || fail "Adjust call failed"

echo "Create order qty=1..."
O_RESP="$(curl_json POST "$API_BASE/api/v1/orders" "${AUTH[@]}" -d '{"items":[{"product_id":"'"$PRODUCT_ID"'","quantity":1}]}')"
ORDER_ID="$(printf '%s' "$O_RESP" | json_get "data.id")"
O_STATUS="$(printf '%s' "$O_RESP" | json_get "data.status")"
if [[ -z "$ORDER_ID" ]]; then fail "Order ID missing"; fi

if [[ "$O_STATUS" != "pending" ]]; then
  fail "Order status unexpected after creation (status=$O_STATUS)"
fi

echo "Pay order..."
curl_json POST "$API_BASE/api/v1/orders/$ORDER_ID/pay" "${AUTH[@]}" -d '{}' >/dev/null || fail "Pay call failed"

INV_FINAL="$(curl_json GET "$API_BASE/api/v1/inventory/$PRODUCT_ID" "${AUTH[@]}")"
AVA_FINAL="$(printf '%s' "$INV_FINAL" | json_get "data.available_quantity")"
RES_FINAL="$(printf '%s' "$INV_FINAL" | json_get "data.reserved_quantity")"

ORDER_FINAL="$(curl_json GET "$API_BASE/api/v1/orders/$ORDER_ID" "${AUTH[@]}")"
O_FINAL_STATUS="$(printf '%s' "$ORDER_FINAL" | json_get "data.status")"

echo "----- SMOKE RESULTS -----"
echo "PRODUCT_ID: $PRODUCT_ID"
echo "ORDER_ID:   $ORDER_ID"
echo "Inventory available: ${AVA_FINAL:-unknown}"
echo "Inventory reserved:  ${RES_FINAL:-unknown}"
echo "Order status:        ${O_FINAL_STATUS:-unknown}"

if [[ $FAILED -ne 0 ]]; then
  exit 1
fi

