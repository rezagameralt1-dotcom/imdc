#!/usr/bin/env bash
set -euo pipefail

# Reads login json from stdin and prints token.
# Supports: {data:{token}}, {token}, {data:{access_token}}, {access_token}
BODY="$(cat)"

TOKEN="$(php -r '
$in = file_get_contents("php://stdin");
$j = json_decode($in, true);
if (!is_array($j)) { exit(0); }
$token =
  ($j["data"]["token"] ?? null) ?:
  ($j["token"] ?? null) ?:
  ($j["data"]["access_token"] ?? null) ?:
  ($j["access_token"] ?? null) ?:
  "";
echo $token;
' <<<"$BODY")"

if [[ -z "${TOKEN}" ]]; then
  echo "[SMOKE][ERROR] Token missing. Login response was:" 1>&2
  echo "$BODY" 1>&2
  exit 1
fi

echo "$TOKEN"
