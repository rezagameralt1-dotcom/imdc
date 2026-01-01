# مستندات ماژول مارکت‌پلیس (Marketplace Module)

## نمای کلی

ماژول مارکت‌پلیس شامل سه بخش اصلی است:
- **محصولات (Products)**: مدیریت کالاها با مالکیت فروشنده
- **سفارشات (Orders)**: ایجاد و مدیریت سفارشات با پشتیبانی از idempotency
- **موجودی (Inventory)**: مدیریت موجودی و رزرو کالا
- **حسابداری (Accounting)**: ثبت خودکار سندهای حسابداری (قابل فعال/غیرفعال)

## معماری

### پایگاه داده‌های جداگانه

- **products**: جداول محصولات
- **orders**: جداول سفارشات و آیتم‌های سفارش
- **inventory**: جداول موجودی و رزرو
- **core**: جداول حسابداری و کاربران

### احراز هویت و مجوزها

- احراز هویت: Sanctum Bearer Token
- RBAC: Spatie Permission با guard=sanctum
- مجوزهای مورد نیاز:
  - `products.read`, `products.create`, `products.update`, `products.delete`
  - `orders.read`, `orders.create`, `orders.update`, `orders.cancel`
  - `inventory.read`, `inventory.adjust`, `inventory.reserve`

## API Endpoints

### محصولات (Products)

#### `GET /api/v1/products`
لیست محصولات با فیلتر و صفحه‌بندی

**Query Parameters:**
- `status`: فیلتر بر اساس وضعیت (draft, active, archived)
- `search`: جستجو در نام و SKU
- `per_page`: تعداد در هر صفحه (پیش‌فرض: 15)

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [...],
    "current_page": 1,
    "per_page": 15,
    "total": 100
  },
  "trace_id": "..."
}
```

#### `GET /api/v1/products/{id}`
نمایش جزئیات یک محصول

#### `POST /api/v1/products`
ایجاد محصول جدید

**Request Body:**
```json
{
  "sku": "PROD-001",
  "name": "نام محصول",
  "description": "توضیحات",
  "price": 100.00,
  "currency": "USD",
  "status": "active",
  "metadata": {}
}
```

**نکته:** `seller_id` به صورت خودکار از کاربر احراز هویت شده تنظیم می‌شود.

#### `PUT/PATCH /api/v1/products/{id}`
به‌روزرسانی محصول

**مجوزها:**
- فروشنده فقط می‌تواند محصولات خود را ویرایش کند
- Admin می‌تواند همه محصولات را ویرایش کند

### سفارشات (Orders)

#### `GET /api/v1/orders`
لیست سفارشات

**Query Parameters:**
- `status`: فیلتر بر اساس وضعیت
- `per_page`: تعداد در هر صفحه

#### `GET /api/v1/orders/{id}`
نمایش جزئیات سفارش

#### `POST /api/v1/orders`
ایجاد سفارش جدید

**Request Body:**
```json
{
  "currency": "USD",
  "idempotency_key": "unique-key-123",
  "items": [
    {
      "product_id": "uuid-here",
      "quantity": 2
    }
  ],
  "meta": {}
}
```

**Idempotency:** اگر `idempotency_key` ارسال شود و سفارش با این کلید وجود داشته باشد، همان سفارش قبلی برگردانده می‌شود.

**فرآیند:**
1. ایجاد سفارش در وضعیت `pending`
2. محاسبه مجموع قیمت
3. رزرو موجودی
4. تغییر وضعیت به `reserved`

#### `POST /api/v1/orders/{id}/pay`
پرداخت سفارش

**فرآیند:**
1. بررسی وضعیت سفارش (باید `pending` یا `reserved` باشد)
2. نهایی‌سازی موجودی (کاهش موجودی و آزادسازی رزرو)
3. تغییر وضعیت به `paid`
4. ایجاد سند حسابداری (در صورت فعال بودن)

#### `POST /api/v1/orders/{id}/cancel`
لغو سفارش

**فرآیند:**
1. بررسی وضعیت سفارش
2. آزادسازی موجودی رزرو شده
3. تغییر وضعیت به `canceled`

### موجودی (Inventory)

#### `GET /api/v1/inventory/{productId}`
نمایش موجودی یک محصول

**Response:**
```json
{
  "success": true,
  "data": {
    "product_id": "uuid",
    "available_quantity": 100,
    "reserved_quantity": 10
  },
  "trace_id": "..."
}
```

#### `POST /api/v1/inventory/{productId}/adjust`
تنظیم موجودی

**Request Body:**
```json
{
  "delta": 10
}
```

**نکته:** `delta` می‌تواند مثبت (افزایش) یا منفی (کاهش) باشد. موجودی منفی مجاز نیست.

#### `POST /api/v1/inventory/reserve`
رزرو موجودی برای سفارش

**Request Body:**
```json
{
  "order_id": "uuid",
  "shop_customer_id": "customer-id",
  "product_id": "uuid",
  "qty": 2
}
```

## ویژگی‌های کلیدی

### 1. مالکیت فروشنده (Seller Ownership)

- هر محصول دارای `seller_id` است
- فروشنده فقط می‌تواند محصولات خود را ویرایش/حذف کند
- Admin می‌تواند همه محصولات را مدیریت کند

### 2. Idempotency برای سفارشات

- استفاده از `idempotency_key` برای جلوگیری از ایجاد سفارشات تکراری
- در صورت ارسال مجدد با همان کلید، سفارش قبلی برگردانده می‌شود

### 3. مدیریت موجودی اتمیک

- رزرو موجودی در تراکنش پایگاه داده انجام می‌شود
- جلوگیری از موجودی منفی
- آزادسازی خودکار در صورت لغو سفارش

### 4. حسابداری خودکار (Accounting)

- ایجاد خودکار سند حسابداری هنگام پرداخت سفارش
- قابل فعال/غیرفعال با feature flag
- پیش‌فرض: فعال در محیط local، غیرفعال در production

**تنظیمات:**
```env
ACCOUNTING_SYNC_ENABLED=true  # یا false
```

**جداول حسابداری:**
- `accounting_vouchers`: سندهای حسابداری
- `accounting_voucher_entries`: خطوط سند
- `accounting_ledger`: دفتر کل

## وضعیت‌های سفارش (Order Statuses)

- `draft`: پیش‌نویس
- `pending`: در انتظار پرداخت
- `reserved`: موجودی رزرو شده
- `paid`: پرداخت شده
- `canceled`: لغو شده

## پاسخ‌های استاندارد API

همه endpointها از فرمت استاندارد استفاده می‌کنند:

```json
{
  "success": true,
  "data": {...},
  "error": null,
  "trace_id": "uuid"
}
```

در صورت خطا:
```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "خطای توضیحی",
    "details": {...}
  },
  "trace_id": "uuid"
}
```

## اسکریپت‌های تأیید (Verification Scripts)

### `scripts/verify-marketplace.sh`

اسکریپت تأیید صحت ماژول مارکت‌پلیس:

1. بررسی وجود جداول کلیدی
2. بررسی دسترسی به endpointهای اصلی
3. تست idempotency برای ایجاد سفارش

**استفاده:**
```bash
./scripts/verify-marketplace.sh
```

## Migration ها

### محصولات
- `2026_01_02_000001_add_seller_id_to_products.php`: افزودن فیلد seller_id

### سفارشات
- `2026_01_02_000001_add_idempotency_key_to_orders.php`: افزودن فیلد idempotency_key

### حسابداری
- `2026_01_02_000001_create_accounting_tables.php`: ایجاد جداول حسابداری

**اجرای Migration:**
```bash
php artisan migrate --database=products --path=database/migrations/products
php artisan migrate --database=orders --path=database/migrations/orders
php artisan migrate --database=core --path=database/migrations/core
```

## مثال‌های استفاده

### ایجاد محصول
```bash
curl -X POST http://localhost:8080/api/v1/products \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "sku": "PROD-001",
    "name": "محصول تست",
    "price": 100.00,
    "currency": "USD",
    "status": "active"
  }'
```

### ایجاد سفارش با Idempotency
```bash
IDEMPOTENCY_KEY="order-$(date +%s)"
curl -X POST http://localhost:8080/api/v1/orders \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"currency\": \"USD\",
    \"idempotency_key\": \"${IDEMPOTENCY_KEY}\",
    \"items\": [
      {\"product_id\": \"PRODUCT-UUID\", \"quantity\": 2}
    ]
  }"
```

### پرداخت سفارش
```bash
curl -X POST http://localhost:8080/api/v1/orders/ORDER-UUID/pay \
  -H "Authorization: Bearer TOKEN"
```

## نکات مهم

1. **مالکیت محصولات**: فروشنده فقط می‌تواند محصولات خود را مدیریت کند
2. **Idempotency**: همیشه از `idempotency_key` برای عملیات حساس استفاده کنید
3. **موجودی منفی**: سیستم از موجودی منفی جلوگیری می‌کند
4. **حسابداری**: در production به صورت پیش‌فرض غیرفعال است
5. **Trace ID**: همه درخواست‌ها دارای `trace_id` برای ردیابی هستند

## عیب‌یابی

### سفارش ایجاد نمی‌شود
- بررسی موجودی کافی
- بررسی وضعیت محصول (باید active باشد)
- بررسی مجوزهای کاربر

### موجودی منفی
- سیستم به صورت خودکار از موجودی منفی جلوگیری می‌کند
- بررسی لاگ‌های inventory_movements

### حسابداری کار نمی‌کند
- بررسی `ACCOUNTING_SYNC_ENABLED` در `.env`
- بررسی لاگ‌های Laravel برای خطاهای حسابداری

## پشتیبانی

برای گزارش مشکل یا درخواست ویژگی جدید، از `trace_id` در پاسخ API استفاده کنید.
