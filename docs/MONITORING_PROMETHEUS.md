# مانیتورینگ سبک با Prometheus

دو راه برای خروجی متریک‌ها فراهم شده است:

1) **Endpoint اسکرپ**: `GET /api/metrics` (فرمت `text/plain; version=0.0.4`)  
   - شامل شمارش ردیف‌های جداول کلیدی، وضعیت DB، صف‌ها و سفارش‌ها به تفکیک وضعیت.  
   - کش ۱۰ثانیه‌ای برای جلوگیری از فشار به DB.

2) **دستور Artisan**: `php artisan imdc:metrics [--output=/path/to/metrics.prom]`  
   - مناسب **Node Exporter textfile collector**:
     ```bash
     # مثال کران هر دقیقه
     * * * * * php /var/www/imdc/artisan imdc:metrics --output=/var/lib/node_exporter/textfile_collector/imdc.prom
     ```

> توجه: این متریک‌ها بدون PII هستند و فقط آمار کلّی را منتشر می‌کنند.

## تست سریع
```bash
curl -sS http://localhost/api/metrics | head -n 30
php artisan imdc:metrics
```
