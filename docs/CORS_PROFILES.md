# CORS Profiles (Per-route)

این بسته، **CORS قابل‌پیکربندی** با پروفایل‌های نام‌گذاری‌شده و نگاشت بر اساس **پیشوند مسیر** فراهم می‌کند:
- پروفایل‌ها: `default`, `strict`, `docs`
- نگاشت مسیر: `config/cors.php` → `routes` (اولین تطبیق برنده است)
- میدل‌ویر: `AdvancedCors` که preflight (`OPTIONS`) را پاسخ می‌دهد و هدرها را روی پاسخ‌ها اعمال می‌کند.

## تنظیمات `.env`
```
CORS_ENABLE=true
CORS_ALLOW_ORIGIN=*
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOW_HEADERS=Content-Type,Authorization,X-Requested-With,X-Trace-Id,X-User-Id
CORS_EXPOSE_HEADERS=X-RateLimit-Limit,X-RateLimit-Remaining,Retry-After,Trace-Id
CORS_ALLOW_CREDENTIALS=false
CORS_MAX_AGE=600

# پروفایل‌های خاص (اختیاری)
CORS_STRICT_ORIGINS=
CORS_DOCS_ORIGINS=*
```

> اگر می‌خواهید برای یک دامنه مشخص باز کنید:  
> `CORS_ALLOW_ORIGIN=https://app.example.com`  
> یا با wildcard: `https://*.example.com`

## تست سریع
```bash
# Preflight
curl -i -X OPTIONS http://localhost/api/health \
 -H "Origin: http://localhost:5173" \
 -H "Access-Control-Request-Method: GET"

# Request معمولی با Origin
curl -i http://localhost/api/health -H "Origin: http://localhost:5173"
```
