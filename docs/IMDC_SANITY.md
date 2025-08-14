# IMDC Sanity & Consistency

این بسته موارد زیر را اضافه می‌کند:
- `App\Support\ApiResponse` استانداردسازی خروجی JSON (با trace_id)
- دستور Artisan: `imdc:sanity` برای چک‌های سریع محیط (DB/Storage/Routes/Queue/Mail)
- اسکریپت HTTP: `scripts/sanity/sanity_check.php` برای تست سرویس‌های هلس

## اجرا
```bash
php artisan imdc:sanity
php artisan imdc:sanity --json | jq .

php scripts/sanity/sanity_check.php http://localhost/api
```
اگر موردی قرمز شد، خروجی را برای رفع هماهنگ‌سازی ارسال کنید.
