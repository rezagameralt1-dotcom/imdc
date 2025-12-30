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

: "${BASE_URL:=http://127.0.0.1:8000}"
: "${IMDC_ADMIN_EMAIL:=${EMAIL:-}}"
: "${IMDC_ADMIN_PASSWORD:=${PASSWORD:-}}"

[[ -n "${IMDC_ADMIN_EMAIL:-}" ]] || { echo "ERROR: EMAIL is required"; exit 1; }
[[ -n "${IMDC_ADMIN_PASSWORD:-}" ]] || { echo "ERROR: PASSWORD is required"; exit 1; }

export BASE_URL IMDC_ADMIN_EMAIL IMDC_ADMIN_PASSWORD
