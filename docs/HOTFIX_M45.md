# M45 Hotfix Pack (Robot-compatible)

این بسته نسخهٔ سازگار با ربات از هات‌فیکس‌های قبلی است (فقط **REPLACE**؛ بدون `PATCH`) و همچنین ParseError دستور پیشرفت را رفع می‌کند.

## چه چیزهایی اصلاح شد؟
1) **ImdcFeature.php** → حذف نگارش نامعتبر `return ..., 1;` و جایگزینی با بلوک‌های استاندارد.
2) **ImdcProgress.php** → حذف `??` داخل اینترپولیشن و استفاده از متغیر میانی `$remote`.
3) **jobs migration** → اگر جدول وجود داشت، **Skip** (به‌جای ساخت مجدد).
4) **posts.content** → اضافه‌شدن ستون فقط در صورت نبودن.
5) **AdminFromEnvSeeder** → ایمپورت صحیح `Schema` و ایمن‌سازی اتصال نقش ادمین.

## اجرا
```bash
cd "/home/reza/Desktop/New Folder/finalrobotcreator"
python3 imdc_robot.py "m45_hotfix_feature_pack.txt"

cd /var/www/imdc
php artisan optimize:clear
php artisan migrate --force

# ادمین (اختیاری):
# ADMIN_EMAIL=admin@example.com
# ADMIN_PASSWORD=StrongPass123!
php artisan db:seed --class=Database\\Seeders\\AdminFromEnvSeeder

# درصد پیشرفت:
php artisan imdc:progress
php artisan imdc:progress --json | jq .
```
