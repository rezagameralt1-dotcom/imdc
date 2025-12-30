#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$DIR/.env"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
fi

rand_email() { printf "imdc-smoke-%s-%s@example.com" "$(date +%s)" "$RANDOM"; }

: "${BASE_URL:=http://127.0.0.1:8000}"
if [[ -z "${EMAIL:-}" && -z "${IMDC_ADMIN_EMAIL:-}" ]]; then
  EMAIL="$(rand_email)"
fi

if [[ -z "${PASSWORD:-}" && -z "${IMDC_ADMIN_PASSWORD:-}" ]]; then
  PASSWORD="Passw0rd!123"
fi

: "${IMDC_ADMIN_EMAIL:=${EMAIL:-}}"
: "${IMDC_ADMIN_PASSWORD:=${PASSWORD:-}}"

export BASE_URL IMDC_ADMIN_EMAIL IMDC_ADMIN_PASSWORD
