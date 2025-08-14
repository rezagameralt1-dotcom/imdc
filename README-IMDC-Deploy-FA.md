# راه‌اندازی Docker و ساخت ریلیز — IMDC (M00)

## اجرای محلی
```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

## ساخت خروجی نهایی
```bash
bash scripts/release_zip.sh
```

> کلیدها/گواهی‌ها را در ریپو نگه ندارید. از `.env.example` استفاده کنید و `.env` را کامیت نکنید.
