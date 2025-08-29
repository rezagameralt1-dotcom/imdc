#!/usr/bin/env bash
set -Eeuo pipefail

echo "==> Install prod deps (no-dev, optimized)…"
composer install --no-dev -o

echo "==> Clear & cache…"
php artisan optimize:clear
php artisan optimize
php artisan permission:cache-reset || true

echo "==> Restart PHP-FPM (if present)…"
sudo systemctl restart php8.2-fpm || true

echo "==> Preflight (smoke + inventory sanity)…"
make preflight

echo "✅ Deploy OK"
