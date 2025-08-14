# M46 – Fix failed_jobs migration & progress route-check

## چه چیزی اصلاح شد؟
1) **مایگریشن `failed_jobs`**: اگر جدول وجود داشته باشد، ساخت را **Skip** می‌کند تا خطای `relation "failed_jobs" already exists` پیش نیاید.
2) **چک‌کنندهٔ مسیرها در `ImdcProgress`**: به‌جای بررسی فقط `methods()[0]`، اکنون `in_array` روی تمام متدهای روتر انجام می‌دهد (مشکل HEAD/GET برطرف شد).

## اجرا
```bash
cd "/home/reza/Desktop/New Folder/finalrobotcreator"
python3 imdc_robot.py "m46_fix_failed_jobs_guard_and_progress_routes.txt"

cd /var/www/imdc
php artisan optimize:clear
php artisan migrate --force
php artisan imdc:progress
php artisan imdc:progress --json | jq .
```
