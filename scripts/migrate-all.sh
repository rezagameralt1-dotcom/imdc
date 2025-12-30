#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

php artisan config:clear >/dev/null || true

echo "[1/4] core..."
php artisan migrate --force --database=pgsql     --path=database/migrations/core

echo "[2/4] products..."
php artisan migrate --force --database=products  --path=database/migrations/products

echo "[3/4] orders..."
php artisan migrate --force --database=orders    --path=database/migrations/orders

echo "[4/4] inventory..."
php artisan migrate --force --database=inventory --path=database/migrations/inventory

echo "ALL DONE"
