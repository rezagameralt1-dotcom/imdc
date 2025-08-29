#!/usr/bin/env bash
set -Eeuo pipefail
cd /var/www/imdc
php -r 'require "vendor/autoload.php"; foreach([
 "Spatie\\Permission\\Middleware\\PermissionMiddleware",
 "Spatie\\Permission\\Middleware\\RoleMiddleware",
 "Spatie\\Permission\\Middleware\\RoleOrPermissionMiddleware",
] as $c){ if(!class_exists($c)){fwrite(STDERR,"MISSING: $c\n"); exit(1);} }'
grep -qE 'Spatie\\Permission\\Middleware\\(PermissionMiddleware|RoleMiddleware|RoleOrPermissionMiddleware)' bootstrap/app.php
php artisan optimize:clear >/dev/null
php artisan permission:cache-reset >/dev/null
echo "preflight OK"
