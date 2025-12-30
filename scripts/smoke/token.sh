#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1090
source "$DIR/env.sh"

LOGIN_JSON="$(curl -sS -X POST "$BASE_URL/api/v1/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$IMDC_ADMIN_EMAIL\",\"password\":\"$IMDC_ADMIN_PASSWORD\"}")"

TOKEN="$(printf '%s' "$LOGIN_JSON" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["token"] ?? ($d["data"]["access_token"] ?? "");')"

if [[ -z "${TOKEN:-}" ]]; then
  echo "ERROR: token missing; login response below:" >&2
  echo "$LOGIN_JSON" >&2
  exit 1
fi

export TOKEN
echo "TOKEN_LEN=${#TOKEN}"
echo "TOKEN_PREFIX=[${TOKEN:0:18}]"
