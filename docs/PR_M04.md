# PR: M04 NFT Ownership Module

## Scope

این PR ماژول NFT Ownership (M04) را با الگوی ERC-721 پیاده‌سازی می‌کند. این ماژول شامل:

1. **ERC-721-like Ownership Registry**: ثبت مالکیت توکن‌های NFT در دیتابیس (off-chain, authoritative)
2. **Idempotent Transfer Flow**: انتقال توکن‌ها با پشتیبانی از `Idempotency-Key` header
3. **WORM Log**: لاگ append-only برای رویدادهای زنجیره‌ای (mint, transfer) با hash chain

### فایل‌های جدید

- `app/Nfts/Models/`: NftToken, NftTransfer, WormLog
- `app/Nfts/Services/`: NftMintService, NftTransferService
- `app/Nfts/Http/Controllers/`: NftController
- `app/Nfts/Http/Requests/`: MintNftRequest, TransferNftRequest
- `app/Nfts/Policies/`: NftPolicy
- `app/Services/Worm/`: WormLogService
- `database/migrations/nfts/`: 3 migration files
- `config/nft.php`: Feature flag configuration
- `scripts/verify-nft.sh`: Guardrail script
- `docs/M04-NFT.md`: Documentation

### فایل‌های تغییر یافته

- `config/database.php`: اضافه شدن connection `nfts`
- `routes/api.php`: اضافه شدن NFT routes (conditional)
- `database/seeders/CoreRbacSeeder.php`: اضافه شدن permissions NFT
- `app/Providers/AuthServiceProvider.php`: اضافه شدن NftPolicy
- `scripts/verify-lock.sh`: اضافه شدن conditional NFT guardrail
- `backend/infra/docker/docker-compose.yml`: اضافه شدن environment variables برای nfts database

### فایل‌های مرتبط با M03 (idempotency fix)

- `app/Orders/Http/Requests/CreateOrderRequest.php`: پشتیبانی از Idempotency-Key header
- `app/Orders/Models/Order.php`: اضافه شدن idempotency_key به fillable
- `app/Orders/Services/OrderService.php`: بهبود idempotency logic
- `database/migrations/orders/2026_01_01_135617_add_unique_idempotency_key_index_to_orders.php`: Migration برای idempotency

## Feature Flag Behavior

ماژول NFT با feature flag `FEATURE_NFT` کنترل می‌شود:

### `FEATURE_NFT=false` (default)

- NFT routes **ثبت نمی‌شوند** در `routes/api.php`
- `verify-nft.sh` با پیام "SKIPPED" خروج می‌کند (exit code 0)
- `verify-lock.sh` NFT guardrail را skip می‌کند
- هیچ تغییری در رفتار API موجود ایجاد نمی‌شود

### `FEATURE_NFT=true`

- NFT routes فعال می‌شوند:
  - `POST /api/v1/nfts/mint`
  - `POST /api/v1/nfts/transfer`
  - `GET /api/v1/nfts/tokens`
- `verify-nft.sh` تست‌های کامل را اجرا می‌کند
- `verify-lock.sh` NFT guardrail را اجرا می‌کند

**نکته:** Feature flag در `config/nft.php` تعریف شده و از `env('FEATURE_NFT', false)` استفاده می‌کند.

## Database Changes

### New Database Connection

- Connection name: `nfts`
- Database: `imdc_nfts` (default)
- Host: `db` (docker compose service)

### New Tables

1. **nfts_tokens**: جدول توکن‌های NFT
   - Unique constraint: `(contract, token_id)`
   - Indexes: `owner_user_id`, `(contract, token_id)`

2. **nfts_transfers**: جدول انتقال‌های NFT
   - Foreign key: `token_id` → `nfts_tokens.id`
   - Indexes: `from_user_id`, `to_user_id`, `idempotency_key`, `requested_by_user_id`

3. **worm_logs**: جدول WORM log
   - Append-only (protected by database trigger)
   - Hash chain: `hash = SHA-256(prev_hash + canonical_json + event_type + entity_type + entity_id + created_at)`
   - Indexes: `event_type`, `(entity_type, entity_id)`, `prev_hash`, `hash`

### Migration Instructions

```bash
php artisan migrate --database=nfts --path=database/migrations/nfts
```

## RBAC Changes

### New Permissions

- `nft.mint`: برای mint کردن توکن
- `nft.transfer`: برای transfer کردن توکن
- `nft.read`: برای خواندن توکن‌ها

### Role Assignments

- **Admin**: تمام permissions (`nft.mint`, `nft.transfer`, `nft.read`)
- **Manager**: فقط `nft.read`

Permissions در `CoreRbacSeeder` اضافه شده‌اند.

## How to Verify

### 1. با Feature Flag غیرفعال (default)

```bash
# verify-lock.sh باید PASS شود (NFT skip می‌شود)
./scripts/verify-lock.sh

# verify-nft.sh باید SKIPPED شود
./scripts/verify-nft.sh
```

**Expected output:**
```
FEATURE_NFT is not enabled (FEATURE_NFT=false)
SKIPPED: NFT verification
```

### 2. با Feature Flag فعال

```bash
# تنظیم feature flag
export FEATURE_NFT=true

# اجرای migrations
docker compose -f backend/infra/docker/docker-compose.yml exec app \
  php artisan migrate --database=nfts --path=database/migrations/nfts

# اجرای NFT guardrail
docker compose -f backend/infra/docker/docker-compose.yml exec app \
  sh -lc "cd /var/www/html && FEATURE_NFT=true ./scripts/verify-nft.sh --in-container"

# اجرای lock verification (شامل NFT guardrail)
FEATURE_NFT=true ./scripts/verify-lock.sh
```

**Expected output from verify-nft.sh:**
```
=== NFT Verification (Official Guardrail) ===
✓ NFTs migrations complete
✓ Token minted
✓ NFT transferred
✓ Idempotency works: same transfer ID
✓ WORM chain integrity verified
=== NFT Guardrail PASSED ===
```

### 3. Manual API Testing

```bash
# 1. Mint token
curl -X POST http://localhost:8080/api/v1/nfts/mint \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"contract":"test","token_id":"1","owner_user_id":"USER-UUID"}'

# 2. Transfer token (با Idempotency-Key)
IDEMPOTENCY_KEY="test-$(date +%s)"
curl -X POST http://localhost:8080/api/v1/nfts/transfer \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: ${IDEMPOTENCY_KEY}" \
  -d '{"token_uuid":"TOKEN-UUID","to_user_id":"NEW-OWNER-UUID"}'

# 3. Repeat transfer (idempotency test)
curl -X POST http://localhost:8080/api/v1/nfts/transfer \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: ${IDEMPOTENCY_KEY}" \
  -d '{"token_uuid":"TOKEN-UUID","to_user_id":"NEW-OWNER-UUID"}'
# باید همان transfer ID را برگرداند (HTTP 200)
```

## Breaking Changes

**NONE** - تمام تغییرات additive هستند و backward compatible.

- Feature flag به صورت پیش‌فرض `false` است
- Routes فقط زمانی ثبت می‌شوند که feature flag فعال باشد
- هیچ تغییری در API موجود ایجاد نمی‌شود

## Testing Checklist

- [x] Feature flag غیرفعال: Routes ثبت نمی‌شوند
- [x] Feature flag فعال: Routes ثبت می‌شوند
- [x] Migrations اجرا می‌شوند بدون خطا
- [x] Mint token کار می‌کند
- [x] Transfer token کار می‌کند
- [x] Idempotency: همان Idempotency-Key همان transfer را برمی‌گرداند
- [x] WORM log chain integrity verified
- [x] verify-nft.sh PASS می‌شود
- [x] verify-lock.sh PASS می‌شود (با و بدون feature flag)
- [x] RBAC permissions کار می‌کنند
- [x] No linter errors

## Related Issues

- M04 NFT Ownership Module implementation
- M03 Orders idempotency fix (included in this PR)

---

**Status**: ✅ READY FOR REVIEW
