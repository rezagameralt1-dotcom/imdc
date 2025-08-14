# M47 – PostgreSQL-safe Backfill & Core Endpoints

## Fixes
- جایگزینی `UPDATE ... LIMIT` با حلقهٔ **batch WHERE IN (ids)** برای PostgreSQL.
- اضافه‌شدن کنترلرهای هسته‌ای (Health/OpenAPI/RBAC/Post/Wallet/Metrics) تا روت‌های کلیدی همیشه وجود داشته باشند.
- مایگریشن تضمینی برای `feature_flags` در صورت نبود.

## اجرا
```bash
cd "/home/reza/Desktop/New Folder/finalrobotcreator"
python3 imdc_robot.py "m47_fix_backfill_and_core_endpoints.txt"

cd /var/www/imdc
php artisan optimize:clear
php artisan migrate --force

# بررسی روت‌ها و درصد:
php artisan route:list | grep -E "api/health|api/metrics|api/rbac/roles|api/social/posts|api/identity/wallets" -n
php artisan imdc:progress
```
