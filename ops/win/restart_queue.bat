@echo off
REM This is a minimal helper for local dev. In production use Supervisor/PM2.
taskkill /F /IM php.exe /T
timeout /T 2 >nul
cd /d "%~dp0..\.."
start "Laravel Queue Worker" php artisan queue:work --tries=3 --backoff=3
echo Queue worker restarted.

