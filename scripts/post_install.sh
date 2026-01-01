#!/usr/bin/env bash
set -euo pipefail

# Run migrations per-domain with explicit paths to avoid cross-DB pollution
php artisan migrate --force --database=core --path=database/migrations/core
php artisan migrate --force --database=products --path=database/migrations/products
php artisan migrate --force --database=orders --path=database/migrations/orders
php artisan migrate --force --database=inventory --path=database/migrations/inventory

# Seed admin user on core database
php artisan db:seed --class=AdminUserSeeder --database=core --force

echo "Done. Admin user: admin@imdc.local / Admin#12345"
echo "Issue a token:"
echo "curl -X POST http://127.0.0.1/api/auth/token -H 'Accept: application/json' -d 'email=admin@imdc.local&password=Admin#12345&device_name=local'"
