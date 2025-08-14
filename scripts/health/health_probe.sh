#!/usr/bin/env bash
set -euo pipefail

BASE="${1:-http://localhost/api}"
TIMEOUT="${TIMEOUT:-5}"

echo "== IMDC Health Probe =="
echo "Base: ${BASE}"

echo "-- Liveness"
curl -fsS --max-time "${TIMEOUT}" "${BASE}/health/live" || { echo "liveness failed"; exit 1; }
echo "OK"

echo "-- Readiness"
RDY=$(curl -fsS --max-time "${TIMEOUT}" "${BASE}/health/ready") || { echo "readiness failed"; exit 1; }
echo "${RDY}" | grep -q '"ready":true' && echo "Ready" || { echo "Not Ready"; exit 2; }
