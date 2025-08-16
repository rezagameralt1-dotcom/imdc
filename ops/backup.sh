#!/usr/bin/env bash
set -euo pipefail

# Usage: ./ops/backup.sh
# Requires: docker compose with service "db"
STAMP=$(date +"%Y%m%d_%H%M%S")
OUT="backup_${STAMP}.sql.gz"
DB_NAME="${DB_DATABASE:-imdc}"
DB_USER="${DB_USERNAME:-imdc}"
DB_PASS="${DB_PASSWORD:-password}"

echo "[*] Starting backup of database: $DB_NAME"
docker exec -e PGPASSWORD="$DB_PASS" imdc_db pg_dump -U "$DB_USER" "$DB_NAME" | gzip -9 > "$OUT"
echo "[*] Backup written to $OUT"
echo "[*] SHA256:"
sha256sum "$OUT" || shasum -a 256 "$OUT" || true
