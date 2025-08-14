# Supervisor (Queue & Schedule) و لاگ‌های سازگار با Fail2ban/ELK

این بسته:
- فایل‌های کانفیگ **Supervisor** برای اجرای پایدار ورکر صف و زمان‌بندی لاراول اضافه می‌کند.
- فرمت **JSON Access Log** برای Nginx تعریف می‌کند که شامل `trace_id` (از هدر `X-Trace-Id` یا `request_id`) است؛ مناسب مصرف در ELK/Vector و همچنین fail2ban.

## راه‌اندازی Supervisor (روی سرور)

1) نصب Supervisor (Ubuntu/Debian):
```bash
sudo apt-get update && sudo apt-get install -y supervisor
```
2) کپی فایل‌های کانفیگ:
```bash
sudo mkdir -p /etc/supervisor/conf.d
sudo cp -v supervisor/imdc-queue.conf /etc/supervisor/conf.d/
sudo cp -v supervisor/imdc-schedule.conf /etc/supervisor/conf.d/
```
3) ری‌لود و استارت:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## لاگ‌های Nginx (JSON)

- مسیرها (داخل کانتینر Nginx):  
  - Access: `/var/log/nginx/imdc_access.log`  
  - Error:  `/var/log/nginx/imdc_error.log`

> فیلد `trace_id` از هدر `X-Trace-Id` یا `request_id` پر می‌شود و با Traceهای اپ هم‌خوان است.

## نکات

- برای fail2ban: می‌توانید فیلتر سفارشی بر اساس `status` و الگوهای حمله (۴۰۳/۴۲۹/۴۰۱ زیاد) روی `imdc_access.log` بنویسید.
- اگر Docker Compose dev را استفاده می‌کنید، این کانفیگ Nginx جایگزین نسخهٔ قبلی شده و JSON log را فعال می‌کند.
