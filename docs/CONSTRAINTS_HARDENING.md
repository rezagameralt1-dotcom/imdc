# Data Backfill & Constraints Hardening

این مایگریشن:
- داده‌های NULL یا نامعتبر را به مقادیر امن backfill می‌کند (مثل `orders.total=0`, `products.price=0`, `orders.status='pending'`, متن پیام‌ها خالی).
- محدودیت‌ها را اضافه می‌کند: `UNIQUE`‌ها (مثل `products.sku`, `wallets.address`, `did_profiles.did`, رأی یکتا در `votes`)،
  کلیدهای خارجی با سیاست منطقی (`CASCADE`/`SET NULL`)، و `CHECK` برای مقادیر مثبت.
- فقط زمانی `NOT NULL` را ست می‌کند که هیچ مقدار NULL باقی نمانده باشد.

> نکته: نام هر کانسترینت ثابت است (مثل `products_sku_unique`). اگر قبلاً وجود داشته باشد، از اضافه کردن صرف‌نظر می‌شود.

## اجرا
```bash
php artisan migrate --force
```

اگر در حین `ALTER TABLE ... SET NOT NULL` اروری دیدید، یعنی هنوز داده‌ی NULL در آن ستون هست؛ خروجی دقیق ارور را بفرستید تا فایل backfill تکمیلی تولید شود.
