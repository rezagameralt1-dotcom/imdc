#!/usr/bin/env bash
set -euo pipefail
ROOT="$(pwd)"
ARTIFACT="imdc_release_$(date +%Y%m%d-%H%M%S).zip"

echo "[release] cleaning previous build..."
rm -rf build_release && mkdir -p build_release
rsync -a --exclude ".git" --exclude "node_modules" --exclude "vendor" --exclude "tests" --exclude "storage/logs/*" ./ build_release/

pushd build_release >/dev/null
echo "[release] install composer (no-dev) ..."
if command -v composer >/dev/null; then
  composer install --no-interaction --no-dev --optimize-autoloader
fi

echo "[release] npm ci && build (if present) ..."
if [ -f package.json ]; then
  if command -v npm >/dev/null; then
    npm ci || npm install
    npm run build || true
  fi
fi

echo "[release] remove dev/test files ..."
rm -f phpunit.xml || true
rm -rf tests || true

echo "[release] optimize laravel ..."
if [ -f artisan ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

echo "[release] zip artifact ..."
cd ..
zip -r "$ARTIFACT" build_release >/dev/null
echo "[release] done: $ARTIFACT"
