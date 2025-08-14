# راهنمای نصب سریع IMDC (نسخهٔ انتشار)

این راهنما برای نصب نسخهٔ زیپ‌شدهٔ انتشار است.

## پیش‌نیازها
- PHP 8.2+ با اکستنشن‌های معمول (`openssl, pdo, pdo_pgsql, mbstring, tokenizer, xml, ctype, json, fileinfo`)
- PostgreSQL 15+
- Composer (برای نصب وابستگی‌های PHP روی سرور مقصد)
- (اختیاری) Node.js 20+ اگر می‌خواهید فرانت را خودتان Build کنید

## مراحل نصب
1) دریافت پکیج زیپ انتشار (`storage/releases/*.zip`) و انتقال به سرور.
2) استخراج:
   ```bash
   unzip imdc-release-*.zip -d /var/www/imdc
   cd /var/www/imdc
   ```
3) ساخت فایل محیط:
   ```bash
   cp .env.example .env
   php artisan key:generate
   # مقادیر دیتابیس و سایر تنظیمات را در .env تنظیم کنید
   ```
4) اجرای مایگریشن‌ها و سیدرهای پایه:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder
   # اختیاری: ساخت ادمین از ENV
   php artisan db:seed --class=Database\\Seeders\\AdminFromEnvSeeder
   ```
5) کش‌ها و بهینه‌سازی:
   ```bash
   php artisan optimize
   ```
6) تنظیم مالکیت/سطوح دسترسی:
   ```bash
   chown -R www-data:www-data /var/www/imdc/storage /var/www/imdc/bootstrap/cache
   chmod -R ug+rwX /var/www/imdc/storage /var/www/imdc/bootstrap/cache
   ```

## وب‌سرور
- برای Nginx، مسیر روت را روی `public/` تنظیم کنید و PHP-FPM را به اسکریپت `public/index.php` وصل نمایید.

## مستندات API
- OpenAPI JSON: `GET /api/health/openapi`
- UI مستندات: `GET /api/docs`

## نکات امنیتی
- `APP_DEBUG=false` در محیط Production
- مقدارهای CORS/RateLimit را در `.env` تنظیم کنید.
- حتماً اتصال HTTPS را فعال کنید.
