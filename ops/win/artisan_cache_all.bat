@echo off
cd /d "%~dp0..\.."
php artisan route:clear
php artisan route:cache
php artisan config:clear
php artisan config:cache
php artisan view:clear
php artisan view:cache
echo Done.

