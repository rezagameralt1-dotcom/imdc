# گزارش خطا با Sentry (Server)

این بسته، ادغام Sentry را **اختیاری** و بدون خطای زمان اجرا اضافه می‌کند:
- اگر پکیج نصب باشد و DSN ست شده باشد، خطاها به Sentry ارسال می‌شوند.
- اگر نباشد، هیچ خطای اضافه‌ای تولید نمی‌شود (no-op).

## نصب پکیج
روی سرور/محیط توسعه:
```bash
composer require sentry/sentry-laravel
```

سپس **Service Provider** خودکار رجیستر می‌شود (از طریق package discovery). ما همچنین `ErrorReportingServiceProvider` را اضافه کرده‌ایم تا بایند `sentry` فراهم باشد.

## تنظیمات `.env`
```
SENTRY_LARAVEL_DSN=
SENTRY_ENV=production
SENTRY_RELEASE=
```
> می‌توانید به‌جای `SENTRY_LARAVEL_DSN` از `SENTRY_DSN` استفاده کنید.

## میدل‌ویر کانتکست
- `SentryContext` به زنجیرهٔ API اضافه شده و تگ‌های `trace_id`, `path`, `method` و user (`X-User-Id`) را به هر رخداد اضافه می‌کند.

## تست سریع
- ابتدا DSN را ست کنید و پکیج را نصب کنید.
- سپس در محیط local به مسیر زیر بروید:
```
GET /debug/sentry
```
باید یک رخداد تست در Sentry ببینید.

> برای فرانت‌اند، اگر بعداً نیاز داشتی، فایل جداگانه برای SDK جاوااسکریپت (Sentry Browser) می‌فرستم.
