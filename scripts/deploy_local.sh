#!/usr/bin/env bash
set -euo pipefail

REF="${1:-v0.3.10}"   # اگر آرگومان ندی، پیش‌فرض v0.3.10
echo ">>> Using ref: ${REF}"

cd /var/www/imdc

echo ">>> Fetch & checkout"
git fetch --all --tags
if git rev-parse -q --verify "refs/tags/${REF}" >/dev/null; then
  git checkout -f "tags/${REF}"
else
  git checkout -f "${REF}" || git checkout -f -B "${REF}"
  git reset --hard "origin/${REF}" || true
fi

echo ">>> Backend deps (no-dev) & optimize"
composer install --no-interaction --no-progress --prefer-dist --no-dev
php artisan config:clear || true
php artisan cache:clear || true

echo ">>> Frontend build (Vite)"
if [ -d frontend ]; then
  pushd frontend >/dev/null
  npm ci
  npm run build
  rsync -a dist/ ../public/spa/
  popd >/dev/null
fi

echo ">>> DB migrate & caches"
php artisan migrate --force
php artisan route:cache || true
php artisan config:cache || true
php artisan view:cache || true

echo ">>> Permissions"
chown -R www-data:www-data storage bootstrap/cache public/spa || true
find storage -type d -exec chmod 775 {} \; || true
find storage -type f -exec chmod 664 {} \; || true

echo ">>> Reload services"
sudo systemctl reload php8.2-fpm || true
sudo systemctl reload nginx || true

echo ">>> DONE (local deploy)"
