# IMDC API Quick Guide

> تمام پاسخ‌های API به‌صورت استاندارد
> 
> ```json
> { "success": true|false, "data" | "error", "trace_id": "..." }
> ```
> 
> بازگردانده می‌شوند. برای ترِیس بهتر، هدر `X-Trace-Id` را در تمام درخواست‌ها بفرستید.

## Health
- `GET /api/health` → پایش سرویس

## RBAC
- `GET /api/rbac/roles` → لیست نقش‌ها
- `POST /api/rbac/users/{userId}/roles/{roleId}` → اتصال نقش به کاربر

## Social
- `GET /api/social/posts` → لیست پست‌ها (paginate)
- `POST /api/social/posts` → ساخت پست
- `GET /api/social/messages` → فیلتر بر اساس `sender_id`/`receiver_id`/`safe_room_id`
- `POST /api/social/messages` → ارسال پیام (حداقل یکی از `receiver_id` یا `safe_room_id` لازم است)
- `DELETE /api/social/messages/{id}` → حذف پیام
- `GET /api/social/safe-rooms` / `POST /api/social/safe-rooms`
- `PATCH /api/social/safe-rooms/{id}/panic` → ست‌کردن `panic_code`

## Marketplace
- `GET /api/market/shops` / `POST /api/market/shops`
- `GET /api/market/products` → فیلتر: `shop_id`, `search`, `per_page`
- `POST /api/market/products`
- `PATCH /api/market/products/{id}` / `DELETE /api/market/products/{id}`
- `POST /api/market/inventory/{productId}/adjust` → افزایش/کاهش موجودی با تراکنش
- `GET /api/market/orders` → فیلتر: `user_id`, `status`
- `POST /api/market/orders`
- `POST /api/market/orders/{orderId}/items` → افزودن آیتم (Observer موجودی و total را هندل می‌کند)
- `PATCH /api/market/orders/{orderId}/status` → وضعیت معتبر: `pending|paid|shipped|cancelled|refunded`

## Feature Flags
- فعال/غیرفعال‌سازی مسیرهای `EXCHANGE`/`DAO` از طریق `config/features.php` یا متغیرهای `.env`:
  - `FEATURE_EXCHANGE`, `FEATURE_DAO`, ...

## Demo & Admin
- `AdminFromEnvSeeder`: با `ADMIN_EMAIL` و `ADMIN_PASSWORD` ادمین می‌سازد و نقش Admin را متصل می‌کند.
- `DemoDataSeeder`: دادهٔ نمونهٔ کاربر/پست/مارکت/سفارش/DAO را اضافه می‌کند.

## Quick Test Script
- اسکریپت `docs/IMDC_API_QUICKTEST.sh` درخواست‌های اصلی را پشت سر هم می‌زند.
- اجرا:
  ```bash
  chmod +x docs/IMDC_API_QUICKTEST.sh
  BASE_URL=http://localhost/api ./docs/IMDC_API_QUICKTEST.sh
  ```

## Notes
- دیتابیس: PostgreSQL
- Constraintهای اصلی با مایگریشن «hardening» اعمال شده‌اند (NOT NULL/ CHECK/ UNIQUE).
- برای performance، مایگریشن ایندکس‌ها پس از ایجاد جداول اجرا می‌شود.
