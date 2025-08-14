
@echo off
REM Backend
cd backend && composer install --no-dev --prefer-dist --no-interaction && php artisan config:cache && cd ..
REM Frontend (if exists)
if exist frontend\package.json (
  cd frontend && npm ci --omit=dev && npm run build && cd ..
)
REM Zip artifact
powershell -NoProfile -Command "Compress-Archive -Path * -DestinationPath release-20250812.zip -Force"

