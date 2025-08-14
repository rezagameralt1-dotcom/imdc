#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo "[setup] composer install"
composer install

if [ ! -f .env ]; then
  echo "[setup] creating .env from example"
  cp .env.example .env
fi

echo "[setup] key:generate"
php artisan key:generate || true

echo "[setup] migrate"
php artisan migrate --force || true

echo "[setup] queue tables (if not exist)"
php artisan queue:table || true
php artisan migrate --force || true

echo "[setup] storage link"
php artisan storage:link || true

echo "[setup] done."
