# Docker Dev Stack (IMDC)

این پکیج یک استک توسعه با **Docker Compose** فراهم می‌کند: PHP-FPM (app)، Nginx (web)، PostgreSQL و MailHog.

## اجرا

```bash
# از ریشه پروژه
docker compose -f docker/compose.dev.yml up -d --build

# نصب وابستگی‌ها داخل کانتینر app (فقط بار اول)
docker exec -it imdc-app bash -lc 'composer install && php artisan key:generate && php artisan migrate --force'
```

سرویس‌ها:
- وب اپ: `http://localhost:8080`
- API: `http://localhost:8080/api`
- دیتابیس Postgres: `localhost:5433` (user/pass/db: `imdc`)
- MailHog UI: `http://localhost:8025`

> متغیرهای اتصال DB/SMTP داخل `compose.dev.yml` ست شده و با `.env` سازگارند.

## خاموش کردن
```bash
docker compose -f docker/compose.dev.yml down
```

## نکات
- Queue با درایور database کار می‌کند. برای اجرای ورکر:
  ```bash
  docker exec -it imdc-app php artisan queue:work --queue=mail,default
  ```
- اگر فرانت دارید (Vite)، با `CORS_ALLOW_ORIGIN` روی `http://localhost:5173` هماهنگ شده است.
