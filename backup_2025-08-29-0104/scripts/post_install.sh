#!/usr/bin/env bash
set -euo pipefail

php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force

echo "Done. Admin user: admin@imdc.local / Admin#12345"
echo "Issue a token:"
echo "curl -X POST http://127.0.0.1/api/auth/token -H 'Accept: application/json' -d 'email=admin@imdc.local&password=Admin#12345&device_name=local'"
