# راهنمای نصب و اجرا (IMDC – Level 9)

## پیش‌نیازها (Windows)
- Docker Desktop (یا Postgres/Redis لوکال)
- PHP 8.2 + Composer
- Node.js LTS + npm

## نصب سریع با اسکریپت
```powershell
python C:\xampp\htdocs\finalrobotcreator\finalrobotcreator.py ^
  --input C:\xampp\htdocs\finalrobotcreator\project_dump.txt ^
  --target C:\xampp\htdocs\DigitalCity ^
  --ensure-backend --switch-to-pg --provision docker
```

## نکات
- فایل‌های تست/Spec به‌صورت خودکار حذف/نادیده گرفته می‌شوند.
- خروجی ریلیز بدون devDependencies ساخته می‌شود.

