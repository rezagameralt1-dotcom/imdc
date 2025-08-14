# Fail2ban + آمار لاگ‌ها (IMDC)

این بسته شامل:
- **فیلتر Fail2ban** برای لاگ JSONـی Nginx (`security/fail2ban/filter.d/imdc-nginx-json.conf`)
- **Jail نمونه** برای Ban کردن آی‌پی‌های پرخطا (`security/fail2ban/jail.d/imdc-nginx.conf`)
- **اسکریپت آمار لاگ**: `scripts/logs/imdc_log_stats.py` برای خلاصه‌سازی کدهای وضعیت، IPهای پر درخواست و نرخ در دقیقه

## راه‌اندازی Fail2ban

1) نصب Fail2ban (Ubuntu/Debian):
```bash
sudo apt-get update && sudo apt-get install -y fail2ban
```
2) کپی فایل‌ها:
```bash
sudo mkdir -p /etc/fail2ban/filter.d /etc/fail2ban/jail.d
sudo cp -v security/fail2ban/filter.d/imdc-nginx-json.conf /etc/fail2ban/filter.d/
sudo cp -v security/fail2ban/jail.d/imdc-nginx.conf /etc/fail2ban/jail.d/
```
3) ری‌استارت:
```bash
sudo systemctl restart fail2ban
sudo fail2ban-client status
sudo fail2ban-client status imdc-nginx
```

> regex فیلتر وضعیت‌های 401/403/404/429 و 5xx را هدف می‌گیرد. اگر می‌خواهید 404 را نادیده بگیرید، فیلتر را سفارشی کنید.

## آمار سریع لاگ‌ها

```bash
python3 scripts/logs/imdc_log_stats.py /var/log/nginx/imdc_access.log
```

خروجی شامل Top status codes، IPها و URIها است. برای پردازش‌های دوره‌ای می‌توانید این اسکریپت را در Cron اجرا کنید.
