#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost/api}"
TRACE="$(uuidgen 2>/dev/null || cat /proc/sys/kernel/random/uuid 2>/dev/null || echo TRACE-$$)"
HEADER=(-H "Content-Type: application/json" -H "X-Trace-Id: ${TRACE}")

echo "== IMDC Quick Test =="
echo "Base: ${BASE_URL}"
echo "Trace: ${TRACE}"
echo

# HEALTH
echo "-- Health"
curl -sS "${BASE_URL}/health" "${HEADER[@]}"
echo -e "\n"

# SHOPS (create demo shop for user #1)
echo "-- Create Shop"
curl -sS -X POST "${BASE_URL}/market/shops" "${HEADER[@]}" \
  -d '{"name":"QuickTest Shop","owner_id":1}'
echo -e "\n"

# PRODUCTS
echo "-- Create Product"
PRES="$(curl -sS -X POST "${BASE_URL}/market/products" "${HEADER[@]}" \
  -d '{"shop_id":1,"name":"QuickTest Item","sku":"QT-001","price":990,"meta":{"color":"blue"}}')"
echo "${PRES}"
PID="$(echo "${PRES}" | sed -n 's/.*"id":\s*\([0-9]\+\).*/\1/p' | head -n1)"
echo "Product ID: ${PID}"
echo

echo "-- Adjust Inventory"
curl -sS -X POST "${BASE_URL}/market/inventory/${PID}/adjust" "${HEADER[@]}" \
  -d '{"delta": 10}'
echo -e "\n"

echo "-- List Products"
curl -sS "${BASE_URL}/market/products?search=QuickTest" "${HEADER[@]}"
echo -e "\n"

# ORDERS
echo "-- Create Order"
ORES="$(curl -sS -X POST "${BASE_URL}/market/orders" "${HEADER[@]}" -d '{"user_id":1}')"
echo "${ORES}"
OID="$(echo "${ORES}" | sed -n 's/.*"id":\s*\([0-9]\+\).*/\1/p' | head -n1)"
echo "Order ID: ${OID}"
echo

echo "-- Add Order Item"
curl -sS -X POST "${BASE_URL}/market/orders/${OID}/items" "${HEADER[@]}" \
  -d "{\"product_id\": ${PID}, \"qty\": 2, \"price\": 990}"
echo -e "\n"

echo "-- Set Order Status"
curl -sS -X PATCH "${BASE_URL}/market/orders/${OID}/status" "${HEADER[@]}" \
  -d '{"status":"paid"}'
echo -e "\n"

# SOCIAL: SAFE ROOMS & MESSAGES
echo "-- Create Safe Room"
SRES="$(curl -sS -X POST "${BASE_URL}/social/safe-rooms" "${HEADER[@]}" -d '{"name":"Quick Room"}')"
echo "${SRES}"
SID="$(echo "${SRES}" | sed -n 's/.*"id":\s*\([0-9]\+\).*/\1/p' | head -n1)"
echo "Safe Room ID: ${SID}"
echo

echo "-- Send Message (to room)"
curl -sS -X POST "${BASE_URL}/social/messages" "${HEADER[@]}" \
  -d "{\"sender_id\":1,\"safe_room_id\":${SID},\"body\":\"Hello from QuickTest\"}"
echo -e "\n"

echo "-- List Messages"
curl -sS "${BASE_URL}/social/messages?safe_room_id=${SID}" "${HEADER[@]}"
echo -e "\n"

echo "== DONE :)"

