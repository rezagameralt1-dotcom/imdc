# Progress Audit (% Completion)

این ابزار یک **درصد تخمینی تکمیل پروژه** می‌دهد بر اساس چک‌لیست baseline ماژول‌ها و فایل‌ها/رُوت‌ها/جداول کلیدی.

## اجرا
```bash
php artisan imdc:progress
php artisan imdc:progress --json | jq .

# یا API:
curl -s http://localhost/api/health/progress | jq .
```

## نحوهٔ محاسبه
- هر آیتم وزنی دارد؛ مجموع امتیاز آیتم‌های موجود تقسیم بر مجموع وزن کل × ۱۰۰.
- گروه‌ها: Core, Security, RBAC, Social, Marketplace, Identity, Infra, DB Hardening, Observability, Ops, Config
- اگر بخشی غیرفعال است اما در آینده اضافه می‌شود، می‌توان وزن آن را در کلاس Command تنظیم کرد.

> توجه: این درصد یک معیار مهندسی داخلی است نه معیار محصولی؛ برای مقایسه با **پروژهٔ مرجع GitHub** می‌توانید گزارش JSON را ضمیمه کنید و آیتم‌های کمبود را با پروژهٔ مرجع یکی‌یکی چک کنید.
