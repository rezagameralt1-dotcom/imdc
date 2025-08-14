# Security Headers & CSP (IMDC)

این بسته، هدرهای امنیتی سخت‌گیرانه و **Content-Security-Policy** قابل‌پیکربندی را اضافه می‌کند.

## هدرهای اعمال‌شده
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()` (قابل تغییر)
- `Cross-Origin-Opener-Policy: same-origin`
- `Cross-Origin-Resource-Policy: same-origin`
- `Cross-Origin-Embedder-Policy` (اختیاری با ENV)
- `Strict-Transport-Security` (فقط روی HTTPS و اگر فعال شود)

## CSP
- به‌صورت پیش‌فرض: `default-src 'self'` و منابع پایه‌ی امن.
- برای صفحهٔ مستندات `/api/docs`، **script-src/style-src** جهت لود Swagger/Redoc بازتر می‌شود.
- حالت `report-only` با `SECURITY_CSP_REPORT_ONLY=true` قابل فعال‌سازی است.

## تنظیمات `.env`
```
# فعال/غیرفعال
SECURITY_CSP_ENABLE=true
SECURITY_CSP_REPORT_ONLY=false

# اجازه‌ی استایل inline (برای صفحات Blade ساده یا UIهای خاص)
SECURITY_CSP_ALLOW_INLINE_STYLE=false

# دامنه‌های مجاز تصویر (CSV)
SECURITY_ALLOWED_IMG_DOMAINS=

# Frame ancestors (مانع کلیک‌جکینگ)
SECURITY_ALLOWED_FRAME_ANCESTORS='none'

# COOP/CORP/COEP (با احتیاط تغییر دهید)
SECURITY_COOP=same-origin
SECURITY_CORP=same-origin
SECURITY_COEP=

# HSTS (فقط اگر HTTPS کامل دارید)
SECURITY_HSTS_ENABLE=false
SECURITY_HSTS_MAXAGE=15552000
SECURITY_HSTS_SUBS=false
SECURITY_HSTS_PRELOAD=false

# اختیاری: گزارش‌گیری
SECURITY_CSP_REPORT_URI=
```

## نکته‌ها
- اگر از Vite/CDN خاص استفاده می‌کنید، دامنهٔ مربوطه را به `script-src`, `style-src`, `connect-src` اضافه کنید.
- برای فرانت مستقل روی پورت 5173، در صورت نیاز، `connect-src` و `CORS` را هماهنگ کنید.
