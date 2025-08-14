# IMDC Backup & Log Retention

این ماژول یک دستور Artisan برای بکاپ دیتابیس PostgreSQL اضافه می‌کند و نگه‌داری (Retention) پشتیبان‌ها و لاگ‌ها را مدیریت می‌کند.

## اجرا دستی

```bash
php artisan imdc:backup             # بکاپ بدون فشرده‌سازی
php artisan imdc:backup --compress  # بکاپ فشرده (gzip)
```

فایل‌ها در مسیر `config('backup.dir')` ذخیره می‌شوند (پیش‌فرض: `storage/app/backups`).

## تنظیمات `.env`

```
# مسیر پوشه‌ی بکاپ (اختیاری)
BACKUP_DIR=storage/app/backups

# مدت نگهداری فایل‌های بکاپ (روز)
BACKUP_RETENTION_DAYS=14

# مدت نگهداری لاگ‌ها (روز)
LOG_RETENTION_DAYS=30

# فعال‌سازی زمان‌بندی بکاپ (اختیاری)
BACKUP_SCHEDULE_ENABLE=false
# ساعت اجرای زمان‌بندی روزانه
BACKUP_SCHEDULE_AT=03:00
```

> توجه: برای اجرای pg_dump باید ابزارهای کلاینت PostgreSQL روی سرور نصب باشد.

## زمان‌بندی (Cron)

در سرور لینوکسی، کرون لازمه. دستور زیر را به کرون کاربر PHP اضافه کنید:

```
* * * * * php /var/www/imdc/artisan schedule:run >> /dev/null 2>&1
```

سپس در `.env` مقدار `BACKUP_SCHEDULE_ENABLE=true` بگذارید تا هر روز ساعت مشخص‌شده بکاپ بگیرد.

## امنیت

- رمز پایگاه‌داده با متغیر محیطی `PGPASSWORD` فقط در محیط اجرای `pg_dump` ست می‌شود و در لاگ‌ها چاپ نمی‌شود.
- فایل‌های بکاپ با تاریخ نام‌گذاری می‌شوند و بیش از مقدار Retention حذف می‌گردند.
