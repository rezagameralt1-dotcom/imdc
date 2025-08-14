# Pre-Deploy Checklist (IMDC)

- [ ] **ENV آماده**: `APP_KEY` ست و `APP_DEBUG=false`
- [ ] **DB**: اتصال Postgres کار می‌کند؛ کاربر/اسکیما دسترسی لازم دارد
- [ ] **Migrations**: `php artisan migrate --force`
- [ ] **Seeders** (اختیاری): `AdminFromEnvSeeder`, `DemoDataSeeder`
- [ ] **Cache Optimize**: `php artisan optimize`
- [ ] **Queue Worker** (در صورت استفاده از ایمیل/صف): `queue:work` زیر سوپروایزر
- [ ] **Permissions**: `storage/` و `bootstrap/cache` دسترسی نوشتن
- [ ] **Health probes**: `/api/health/live` و `/api/health/ready` توسط لود بالانسر چک شوند
- [ ] **Backups**: کران `schedule:run` فعال و `BACKUP_*` تنظیم شده
- [ ] **CORS / RateLimit**: مقادیر `.env` مطابق فرانت‌اِند و ترافیک واقعی
- [ ] **TLS/HTTPS**: گواهی و پیکربندی وب‌سرور بررسی شده

