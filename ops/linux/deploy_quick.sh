#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."

php artisan down || true

composer install --no-dev --prefer-dist --no-interaction
php artisan migrate --force
npm ci --omit=dev || npm install
npm run build || true

php artisan route:clear && php artisan route:cache
php artisan config:clear && php artisan config:cache
php artisan view:clear && php artisan view:cache

php artisan up
echo "Deploy quick done."

