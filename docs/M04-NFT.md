# M04: NFT Ownership Module

## Overview

ماژول NFT Ownership (M04) یک سیستم مدیریت مالکیت توکن‌های NFT با الگوی ERC-721 را پیاده‌سازی می‌کند. این ماژول شامل ثبت مالکیت، انتقال توکن‌ها، و لاگ WORM (Write-Once-Read-Many) برای رویدادهای زنجیره‌ای است.

## Features

### 1. ERC-721-like Ownership Registry
- ثبت مالکیت توکن‌های NFT در دیتابیس (off-chain, authoritative)
- پشتیبانی از mint و transfer
- وضعیت‌های توکن: minted, transferred, burned

### 2. Idempotent Transfer Flow
- انتقال توکن‌ها با پشتیبانی از Idempotency-Key
- جلوگیری از ایجاد transfer تکراری با همان کلید
- مدیریت race condition با unique constraint

### 3. WORM Log
- لاگ append-only برای رویدادهای زنجیره‌ای (mint, transfer, lease-ready)
- Hash chain برای اطمینان از یکپارچگی
- محافظت در سطح دیتابیس (trigger) برای جلوگیری از update/delete

## Database Schema

### nfts_tokens
- `id` (uuid, primary key)
- `contract` (string)
- `token_id` (string)
- `owner_user_id` (uuid)
- `metadata_uri` (string, nullable)
- `status` (enum: minted, transferred, burned)
- `created_at`, `updated_at`

### nfts_transfers
- `id` (uuid, primary key)
- `token_id` (uuid, foreign key to nfts_tokens)
- `from_user_id` (uuid, nullable)
- `to_user_id` (uuid)
- `idempotency_key` (string, nullable)
- `requested_by_user_id` (uuid)
- `status` (enum: pending, committed, rejected)
- `created_at`, `updated_at`

### worm_logs
- `id` (uuid, primary key)
- `event_type` (string: mint, transfer, lease, etc.)
- `entity_type` (string)
- `entity_id` (uuid)
- `payload_json` (jsonb)
- `prev_hash` (string, nullable)
- `hash` (string)
- `created_at` (timestamp)

**WORM Constraints:**
- جدول append-only است (no updates/deletes)
- Hash محاسبه می‌شود: SHA-256(prev_hash + canonical_json(payload) + event_type + entity_type + entity_id + created_at)
- prev_hash = آخرین hash در زنجیره

## API Endpoints

### POST /api/v1/nfts/mint
Mint یک توکن NFT جدید.

**Request Body:**
```json
{
  "contract": "string",
  "token_id": "string",
  "owner_user_id": "uuid",
  "metadata_uri": "string (optional)"
}
```

**Response:** 201 Created
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "contract": "string",
    "token_id": "string",
    "owner_user_id": "uuid",
    "metadata_uri": "string",
    "status": "minted",
    "created_at": "timestamp",
    "updated_at": "timestamp"
  }
}
```

### POST /api/v1/nfts/transfer
انتقال یک توکن NFT.

**Headers:**
- `Idempotency-Key` (required): کلید idempotency برای جلوگیری از transfer تکراری

**Request Body:**
```json
{
  "token_uuid": "uuid",
  "to_user_id": "uuid"
}
```

**Response:** 200 OK (idempotent) or 201 Created (new)
```json
{
  "success": true,
  "data": {
    "transfer": {
      "id": "uuid",
      "token_id": "uuid",
      "from_user_id": "uuid",
      "to_user_id": "uuid",
      "status": "committed",
      "created_at": "timestamp"
    },
    "token": {
      "id": "uuid",
      "owner_user_id": "uuid",
      "status": "transferred"
    }
  }
}
```

### GET /api/v1/nfts/tokens
لیست توکن‌های NFT.

**Query Parameters:**
- `owner_user_id` (optional): فیلتر بر اساس مالک

**Response:** 200 OK
```json
{
  "success": true,
  "data": {
    "data": [...],
    "current_page": 1,
    "per_page": 15
  }
}
```

## Authentication & Authorization

تمام endpoint‌ها نیاز به Sanctum Bearer token دارند.

**Permissions:**
- `nft.mint`: برای mint کردن توکن
- `nft.transfer`: برای transfer کردن توکن
- `nft.read`: برای خواندن توکن‌ها

**Roles:**
- Admin: تمام permissions
- Manager: فقط nft.read

## Feature Flag

ماژول NFT با feature flag کنترل می‌شود:

```env
FEATURE_NFT=true|false  # default: false
```

**When FEATURE_NFT=false:**
- NFT routes ثبت نمی‌شوند
- verify-nft.sh با پیام SKIPPED خروج می‌کند

**When FEATURE_NFT=true:**
- NFT routes فعال می‌شوند
- verify-nft.sh تست‌های کامل را اجرا می‌کند

## Guardrail Script

### scripts/verify-nft.sh

اسکریپت guardrail که تست‌های end-to-end را اجرا می‌کند:

1. بررسی FEATURE_NFT flag
2. اجرای migrations
3. Mint کردن token
4. Transfer کردن token
5. تست idempotency (repeat transfer با همان Idempotency-Key)
6. بررسی WORM log chain integrity

**Usage:**
```bash
FEATURE_NFT=true ./scripts/verify-nft.sh
```

### Integration with verify-lock.sh

`verify-lock.sh` به صورت شرطی `verify-nft.sh` را اجرا می‌کند:

- اگر `FEATURE_NFT=true`: NFT guardrail اجرا می‌شود
- اگر `FEATURE_NFT=false`: NFT guardrail skip می‌شود

## Database Connection

NFT module از connection جداگانه `nfts` استفاده می‌کند:

```php
DB::connection('nfts')
```

**Configuration:**
- Connection name: `nfts`
- Database: `imdc_nfts` (default)
- Host: `db` (docker compose service)

## Services

### NftMintService
- `mint()`: Mint کردن یک توکن جدید
- بررسی duplicate token
- ثبت در WORM log

### NftTransferService
- `transfer()`: Transfer کردن یک توکن
- Idempotency check با Idempotency-Key
- مدیریت race condition
- ثبت در WORM log

### WormLogService
- `log()`: ثبت رویداد در WORM log
- `verifyChain()`: بررسی یکپارچگی hash chain
- محاسبه hash با SHA-256

## Migration

Migrations در `database/migrations/nfts/` قرار دارند:

```bash
php artisan migrate --database=nfts --path=database/migrations/nfts
```

## Testing

برای تست ماژول NFT:

```bash
# Enable feature flag
export FEATURE_NFT=true

# Run guardrail
./scripts/verify-nft.sh

# Or via docker compose
docker compose -f infra/docker/docker-compose.yml exec app \
  sh -lc "cd /var/www/html && FEATURE_NFT=true ./scripts/verify-nft.sh --in-container"
```

## Quickstart

### فعال‌سازی Feature Flag

برای فعال‌سازی ماژول NFT، متغیر محیطی `FEATURE_NFT` را تنظیم کنید:

```bash
# در .env یا docker-compose.yml
FEATURE_NFT=true
```

یا در محیط Docker:

```bash
export FEATURE_NFT=true
```

**نکته:** به صورت پیش‌فرض `FEATURE_NFT=false` است و ماژول غیرفعال است.

### اجرای Migrations

پس از فعال‌سازی feature flag، migrations را اجرا کنید:

```bash
php artisan migrate --database=nfts --path=database/migrations/nfts
```

یا در محیط Docker:

```bash
docker compose -f infra/docker/docker-compose.yml exec app \
  php artisan migrate --database=nfts --path=database/migrations/nfts
```

### اجرای Guardrail Scripts

#### verify-nft.sh

برای تست end-to-end ماژول NFT:

```bash
# در محیط host
FEATURE_NFT=true ./scripts/verify-nft.sh

# در محیط container
docker compose -f infra/docker/docker-compose.yml exec app \
  sh -lc "cd /var/www/html && FEATURE_NFT=true ./scripts/verify-nft.sh --in-container"
```

**خروجی مورد انتظار:**
- ✓ NFTs migrations complete
- ✓ Token minted
- ✓ NFT transferred
- ✓ Idempotency works
- ✓ WORM chain integrity verified
- === NFT Guardrail PASSED ===

**اگر FEATURE_NFT=false:**
- Script با پیام "SKIPPED: NFT verification" خروج می‌کند (exit code 0)

#### verify-lock.sh

اسکریپت `verify-lock.sh` به صورت خودکار `verify-nft.sh` را اجرا می‌کند اگر `FEATURE_NFT=true` باشد:

```bash
# اگر FEATURE_NFT=true: NFT guardrail اجرا می‌شود
# اگر FEATURE_NFT=false: NFT guardrail skip می‌شود
./scripts/verify-lock.sh
```

**نکته:** `verify-lock.sh` همیشه `verify-marketplace.sh` را اجرا می‌کند (مستقل از feature flag).

### مثال استفاده از API

#### Mint NFT Token

```bash
curl -X POST http://localhost:8080/api/v1/nfts/mint \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "contract": "my-contract",
    "token_id": "token-123",
    "owner_user_id": "USER-UUID",
    "metadata_uri": "ipfs://QmHash"
  }'
```

#### Transfer NFT Token

```bash
IDEMPOTENCY_KEY="transfer-$(date +%s)"
curl -X POST http://localhost:8080/api/v1/nfts/transfer \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: ${IDEMPOTENCY_KEY}" \
  -d '{
    "token_uuid": "TOKEN-UUID",
    "to_user_id": "NEW-OWNER-UUID"
  }'
```

**نکته:** Header `Idempotency-Key` الزامی است برای transfer.

#### لیست توکن‌ها

```bash
# همه توکن‌ها
curl -X GET http://localhost:8080/api/v1/nfts/tokens \
  -H "Authorization: Bearer YOUR_TOKEN"

# فیلتر بر اساس مالک
curl -X GET "http://localhost:8080/api/v1/nfts/tokens?owner_user_id=USER-UUID" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Notes

- تمام primary keys از نوع UUID هستند
- WORM log table با trigger محافظت می‌شود (no updates/deletes)
- Transfer flow کاملاً idempotent است
- API response format: `{ success, data|error, trace_id }`
- Feature flag: `FEATURE_NFT` کنترل می‌کند که routes ثبت شوند یا نه
