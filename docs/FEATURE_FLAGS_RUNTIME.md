# Feature Flags (Runtime)

این بسته **Feature Flag**های ران‌تایم را از طریق **DB** فراهم می‌کند و اگر مقدار ENV مثل `FEATURE_EXCHANGE=true` باشد، همان را ترجیح می‌دهد.

## چی اضافه شد؟
- جدول `feature_flags` (کلید یکتا، enabled، meta)
- سرویس `App\Support\FeatureFlags` با کش ۳۰ثانیه
- آپدیت میدل‌ویر `EnsureFeatureEnabled` برای استفاده از DB/ENV
- دستور Artisan:  
  - `php artisan imdc:feature list`
  - `php artisan imdc:feature get EXCHANGE`
  - `php artisan imdc:feature on DAO`
  - `php artisan imdc:feature off DAO`
  - `php artisan imdc:feature toggle DAO`
  - `php artisan imdc:feature set EXCHANGE --meta='{"by":"admin"}'`
- Seeder: `FeatureFlagSeeder` که براساس ENV مقداردهی اولیه می‌کند

## اجرا
```bash
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\FeatureFlagSeeder
php artisan imdc:feature list
```

## استفاده در Route
```php
Route::middleware([\\App\\Http\\Middleware\\EnsureFeatureEnabled::class . ':EXCHANGE'])
  ->get('/api/exchange/health', ...);
```

> اگر ENV برای یک ویژگی ست شده باشد، همان مرجع است و مقدار DB نادیده گرفته می‌شود؛ در غیر این صورت مقدار DB استفاده می‌شود.
