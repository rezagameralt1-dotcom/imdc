@echo off
cd backend && php artisan migrate --force && php artisan db:seed --force

