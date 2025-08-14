# IMDC Release Notes

## v1.0.0 (Bootstrap)
- اسکیمای اصلی Postgres + ایندکس‌های کارایی
- مدل‌ها و ارتباطات Eloquent
- APIهای Social / Marketplace / Identity (DID/Wallet/NFT)
- گاردهای امنیتی: Trace ID، هدرهای امنیتی، CORS، Rate Limit، لاگ PII-safe
- RBAC پایه با RequireRole و فیچر فلگ‌ها
- Observerهای دومِینی (Order total + Inventory)
- Seederهای Admin و Demo
- OpenAPI 3.1 + UI (Swagger/Redoc)
- Backup & Log retention
- Release Builder (ZIP)

### Change Highlights
- **Health**: `/api/health` (ping)، **جدید**: `/api/health/live` و `/api/health/ready` برای liveness/readiness پروب‌ها.
- **Queue/Mail**: صف database + ایمیل وضعیت سفارش.
- **Schema Audit**: دستور `imdc:schema-audit` برای گزارش کمبود ستون/ایندکس.

### Upgrade Notes
- حتماً `php artisan migrate --force` را پس از آپدیت اجرا کنید.
- اگر RateLimit/CORS را تغییر می‌دهید، `.env` را به‌روزرسانی و `php artisan optimize:clear` را اجرا کنید.
- برای صف ایمیل، `queue:work` را اجرا یا سوپروایزر پیکربندی کنید.

