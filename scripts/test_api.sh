#!/usr/bin/env bash
set -euo pipefail
API_BASE="${1:-http://127.0.0.1}"
echo "== Public ping =="
curl -s $API_BASE/api/ping | jq . || true
TOKEN=$(curl -s -X POST $API_BASE/api/auth/token -d "email=admin@imdc.local" -d "password=Admin#12345" -d "device_name=local" | jq -r '.data.token')
echo "TOKEN=$TOKEN"
curl -i $API_BASE/api/secure/ping | head -n1
curl -s -H "Authorization: Bearer $TOKEN" $API_BASE/api/secure/ping | jq . || true
curl -s -H "Authorization: Bearer $TOKEN" $API_BASE/api/admin/ping | jq . || true
