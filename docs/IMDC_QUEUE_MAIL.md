# صف‌ها و ایمیل در IMDC

این بسته:
- جدول‌های `jobs` و `failed_jobs` را ایجاد می‌کند.
- یک Job عمومی `SendMailJob` برای ارسال ایمیل مایل‌ابل‌ها از صف.
- `OrderStatusChanged` (Mailable) و اتصال در `OrderController@setStatus` برای اعلان تغییر وضعیت سفارش.

## راه‌اندازی

1) تنظیمات ایمیل را در `.env` ست کنید (نمونه در پایین).
2) اجرای مایگریشن‌ها:
   ```bash
   php artisan migrate --force
   ```
3) اجرای ورکر صف (مثلاً روی کانکشن database):
   ```bash
   php artisan queue:work --queue=mail,default --sleep=1 --tries=3
   ```
   یا پایدار با سوپروایزر:
   ```
   [program:imdc-queue]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/imdc/artisan queue:work --queue=mail,default --sleep=1 --tries=3 --timeout=120
   autostart=true
   autorestart=true
   numprocs=1
   redirect_stderr=true
   stdout_logfile=/var/www/imdc/storage/logs/queue.log
   stopwaitsecs=3600
   ```

## تنظیمات `.env` برای ایمیل

```
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="no-reply@imdc.local"
MAIL_FROM_NAME="IMDC"
```

> برای توسعه می‌توانید از MailHog یا Mailpit استفاده کنید.

## تست سریع

پس از اجرای ورکر:
```bash
# تغییر وضعیت سفارش و انتظار ایمیل
curl -X PATCH http://localhost/api/market/orders/1/status \
  -H "Content-Type: application/json" -H "X-User-Id: 1" \
  -d '{"status":"paid"}'
```
ایمیل در صندوق (یا MailHog/Mailpit) مشاهده خواهد شد.
