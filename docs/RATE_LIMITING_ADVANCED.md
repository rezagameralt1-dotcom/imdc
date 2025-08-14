# Advanced Rate Limiting (Token Bucket)

این بسته **لیمیت پیشرفته** به‌صورت *Token Bucket* برای هر **IP + محدوده مسیر** اضافه می‌کند:
- ظرفیت *burst* (پیش‌فرض ۶۰)، و *refill* بر اساس `limit_per_min` (پیش‌فرض ۱۲۰ در دقیقه).
- هدرها: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`.
- Override برای Prefix مسیرها (health/metrics/docs).
- استثنا برای IPهای مجاز (مثل 127.0.0.1).

## تنظیمات `.env`
```
RATE_LIMIT_ENABLE=true
RATE_LIMIT_PER_MIN=120
RATE_LIMIT_BURST=60
RATE_LIMIT_EXEMPT_IPS=127.0.0.1
```

برای overrideهای بیشتر، `config/rate.php` را ویرایش کنید.

## تست سریع
در یک ترمینال:
```bash
ab -n 200 -c 20 http://localhost/api/health
# یا با hey:
# hey -n 200 -c 20 http://localhost/api/health
```

در پاسخ‌های 429، هدر `Retry-After` مقدار انتظار را نشان می‌دهد. برای مشاهده مصرف، هدرهای `X-RateLimit-*` را بررسی کنید.
