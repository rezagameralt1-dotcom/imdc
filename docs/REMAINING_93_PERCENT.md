# IMDC Build – Remaining 93% bundle

## Usage
1) این فایل را با نام `project_dump_93pct.txt` در مسیر `C:\xampp\htdocs\finalrobotcreator` ذخیره کنید.
2) اسکریپت را اجرا کنید:
   ```powershell
   py C:\xampp\htdocs\finalrobotcreator\finalrobotcreator.py `
     --input C:\xampp\htdocs\finalrobotcreator\project_dump_93pct.txt `
     --target C:\xampp\htdocs\DigitalCity `
     --switch-to-pg
   ```
   (در صورت تمایل: `--ensure-backend` اگر backend از قبل نبود)
3) سپس:
   ```powershell
   cd C:\xampp\htdocs\DigitalCity\frontend
   npm install
   npm run build
   ```

## یادداشت‌ها
- CORS برای 5173 فعال شده است.
- API های اصلی: /api/health, /api/posts, /api/categories, /api/tags, /api/pages, /api/assets, /api/settings
- برای آپلود فایل، دیسک `public` باید لینک شده باشد (`php artisan storage:link`).

