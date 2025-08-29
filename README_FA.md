# IMDC – اسکلتی برای API + Sanctum (Bearer) + نقش‌ها

این بسته‌ی «اُورلی» روی پروژه‌ی لاراول شما کپی می‌شود و موارد زیر را فراهم می‌کند:
- احراز هویت با **Sanctum Personal Access Token (Bearer)** برای موبایل/کلاینت‌ها
- اندپوینت‌های ثبت‌نام، دریافت توکن، خروج، و «me»
- نقش‌های ساده (User/Admin) + مسیر نمونه‌ی محافظت‌شده با نقش
- فایل نمونه پیکربندی Nginx
- اسکریپت `scripts/post_install.sh` برای مایگریت و سید

## اندپوینت‌ها
- `POST /api/auth/register`  → ساخت کاربر جدید
- `POST /api/auth/token`     → دریافت توکن Bearer با ورودی `{ email, password, device_name }`
- `POST /api/auth/logout`    → حذف توکن فعلی (هدر Authorization لازم)
- `GET  /api/auth/me`        → اطلاعات کاربر لاگین
- `GET  /api/admin/ping`     → فقط برای نقش Admin
- `GET  /api/ping` و `GET /api/secure/ping`

## تست سریع
1) توکن ادمین:
   ```bash
   curl -s -X POST http://127.0.0.1/api/auth/token -H "Accept: application/json"        -d "email=admin@imdc.local&password=Admin#12345&device_name=local" | jq .
   ```
2) تماس محافظت‌شده:
   ```bash
   TOKEN=<خروجی_توکن>
   curl -s http://127.0.0.1/api/admin/ping -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" | jq .
   ```

> توجه: نقش‌ها بسیار ساده‌اند و برای پروژه واقعی می‌توانید در آینده به راهکارهای کامل‌تر ارتقا دهید.
