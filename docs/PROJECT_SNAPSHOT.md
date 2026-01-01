# IMDC Project Snapshot

## Version: v21
## Chat Identifier: IMDC — M04
## Date: 2026-01-02

---

## Locked Rules (Cumulative)

### v1-v19: [Previous locked rules preserved]

### v20 Updates:

**RBAC Guardrails:**
- `scripts/verify-rbac.sh` guard_name checks are schema-aware:
  - Check 3 (roles guard_name normalization): SKIPs with message "SKIP: roles.guard_name column not present in schema (expected in this build)" when column does not exist; runs normalization check when column exists.
  - Check 4 (permissions guard_name normalization): SKIPs with message "SKIP: permissions.guard_name column not present in schema (expected in this build)" when column does not exist; runs normalization check when column exists.
  - Check 5 (duplicate role names): Adapts SQL query based on guard_name column existence (groups by name only if column missing, groups by (guard_name, name) if column exists).
  - Check 6 (duplicate permission names): Adapts SQL query based on guard_name column existence (groups by name only if column missing, groups by (guard_name, name) if column exists).
- Schema detection uses `information_schema.columns` queries before attempting guard_name operations.
- Script does not fail on missing guard_name column; gracefully handles schema variations.

**DID Guardrail:**
- `scripts/verify-did.sh` PASS status confirmed.
- DID guardrail always executes (no skip logic); temporarily enables FEATURE_DID via .env modification during guardrail run, then restores original .env on exit.
- Routes: GET/POST/PUT `/api/v1/did/me` registered and functional.
- Feature gate: `App\Support\DidFeatureGate::isEnabled()` reads `env('FEATURE_DID')` at runtime (bypasses config cache).
- Guardrail creates DID profile, verifies GET/UPDATE operations, and validates database writes.

**Marketplace Guardrail:**
- `scripts/verify-marketplace.sh` PASS status confirmed with real order creation.
- Test 5 (Order Idempotency) is deterministic and NON-SKIP:
  - Uses `scripts/_guardrail/ensure_guardrail_product.php` to auto-ensure a product exists.
  - If no product exists, creates guardrail product with ALL required NOT NULL fields:
    - `sku`: "IMDC-GR-" + first 12 chars of UUID (uppercase, no hyphens)
    - `name`, `price` (10.00), `currency` ("USD"), `status` ("active")
    - `metadata`: `{"seed":"guardrail","purpose":"marketplace-idempotency","run_id":"<uuid>","at":"<iso8601>"}`
  - Uses `scripts/_guardrail/ensure_inventory.php` to ensure inventory availability for the product.
  - Creates real order via HTTP POST `/api/v1/orders` with Idempotency-Key.
  - Creates same order again with identical Idempotency-Key to verify idempotency.
  - Verifies same order ID returned for duplicate idempotency_key requests (real order creation, not mocked).
  - Cleanup trap registered: `scripts/_guardrail/cleanup_guardrail_product.php` deletes guardrail product on EXIT (matches metadata.seed and run_id).
  - Hard-fails with clear message if ensure step fails (no skip).
- Real business order flow verified:
  - Product created via API (non-guardrail).
  - Inventory set via correct schema (`available_quantity` / `reserved_quantity`).
  - Two POST `/api/v1/orders` with same Idempotency-Key returned SAME order_id.
  - DB confirmed order `status=reserved`.
- verify-marketplace.sh PASS: idempotency non-skip.
- Token minting (Test 2): Uses Spatie Admin role lookup via `User::whereHas('roles', function($q) { $q->where('name', 'Admin'); })`; falls back to first user if no Admin found; fails clearly if no users exist.

**NFT Guardrail:**
- NFT M04 implemented behind `FEATURE_NFT` (default: false).
- `scripts/verify-nft.sh` remains SKIPPED when `FEATURE_NFT=false` (expected behavior).
- FEATURE_NFT=true + verify-nft PASS: mint, transfer, idempotency, WORM chain.

**Lock Guardrail:**
- `scripts/verify-lock.sh` PASS status confirmed.
- Includes DID guardrail check (Check 4) that always runs (no FEATURE_DID skip).
- NFT guardrail check (Check 3) reads `FEATURE_NFT` from `.env` file deterministically (no manual export required).
- If `FEATURE_NFT=true` in `.env`, NFT guardrail runs and must PASS (real NFT operations: mint, transfer, idempotency, WORM chain verification).
- If `FEATURE_NFT=false` or missing, NFT guardrail is SKIPPED.
- verify-lock PASS with FEATURE_NFT=true: NFT check real.
- All checks (Marketplace with real order creation, NFT if enabled with real operations, DID, hostname validation) pass.

**Guardrail Source of Truth:**
- Guardrails are the authoritative source of truth for system state.
- Earlier manual schema-inspection commands failed due to terminal/heredoc corruption; guardrails provide reliable verification.

**Terminal Paste Corruption Fix:**
- All guardrail scripts (`verify-marketplace.sh`, `verify-nft.sh`, `verify-did.sh`) use PHP helper files under `scripts/_guardrail/` instead of multi-line heredocs.
- Helper files: `check_db_exists.php`, `check_table_exists.php`, `reset_database.php`, `check_tables_exist.php`, `mint_token_admin.php`, `ensure_inventory.php`, `create_test_user.php`, `count_worm_logs.php`, `verify_did_profile.php`.
- All scripts work with `docker compose exec -T` (non-interactive mode).

### v21 Updates (M04 — NFT Ownership):

**NFT Module Implementation:**
- NFT module fully implemented with feature flag `FEATURE_NFT` (default: false).
- Database connection: `nfts` (database: `imdc_nfts`).
- Tables: `nfts_tokens`, `nfts_transfers`, `worm_logs` (in nfts database).
- Routes registered conditionally when `FEATURE_NFT=true`:
  - `POST /api/v1/nfts/mint` — Mint new NFT token (Idempotency-Key supported)
  - `POST /api/v1/nfts/transfer` — Transfer NFT ownership (Idempotency-Key required/recommended)
  - `GET /api/v1/nfts` — List NFT tokens with pagination (filter by owner_user_id)
  - `GET /api/v1/nfts/{id}` — Get NFT token by UUID or token_id
- Routes are NOT registered when `FEATURE_NFT=false` (feature flag checked in `routes/api.php`).

**NFT Security & Authorization:**
- Mint: Requires Admin role OR `nft.mint` permission (via `NftPolicy::mint()`).
- Transfer: Only token owner can transfer (or Admin) — enforced via `NftPolicy::transfer($user, $token)` with token instance check.
- Read: Requires Admin role OR `nft.read` permission (via `NftPolicy::read()`).
- All endpoints require `auth:sanctum` middleware.

**NFT Idempotency:**
- Mint endpoint: Idempotency-Key supported (optional but recommended).
  - Accepts `idempotency_key` in request body or `Idempotency-Key` header (case-insensitive).
  - Stores idempotency records in `idempotency_keys` table (scope: `nfts.mint`).
  - Returns HTTP 200 on replay (same payload), HTTP 409 on conflict (different payload).
- Transfer endpoint: Idempotency-Key required/recommended.
  - Accepts `idempotency_key` in request body or `Idempotency-Key` header (case-insensitive).
  - Stores idempotency records in `idempotency_keys` table (scope: `nfts.transfer`).
  - Also uses unique constraint on `nfts_transfers.idempotency_key` for database-level idempotency.
  - Returns HTTP 200 on replay (same payload), HTTP 409 on conflict (different payload).
- Request hash validation: Both endpoints hash request payload (excluding idempotency_key) to detect payload mismatches.

**WORM Logging (Canonical & Deterministic):**
- All NFT events (mint, transfer) are logged to `worm_logs` table via `WormLogService`.
- WORM canonicalization uses single source of truth: `App\Support\Worm\WormHasher`.
- Hash input format (delimiter-joined string): `prev_hash + "\n" + event_type + "\n" + occurred_at + "\n" + payload_canonical_json`.
- Payload canonicalization:
  - Recursively sorts object keys (ksort).
  - Normalizes numeric strings to integers (e.g., "54" -> 54) for deterministic type consistency.
  - Preserves list array order.
  - Encoding: `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION`.
- Timestamp canonicalization: ISO8601 UTC with seconds precision: "2026-01-01T21:24:57Z" (microseconds truncated).
- prev_hash chaining (deterministic tail-based):
  - Writer uses same ordering as verifier (`created_at ASC, id ASC`) to identify chain tail.
  - First log in chain: `prev_hash = ""` (empty string).
  - Subsequent logs: `prev_hash = tail log's hash` (last log by `created_at ASC, id ASC`).
  - Writer sets `prev_hash` BEFORE computing hash.
- Guardrail ensures distinct `occurred_at` seconds between events (1-second sleep) to prevent ordering ambiguity.
- Hash algorithm: SHA-256 (hex, lowercase).
- WORM logs are append-only (protected by database trigger).

**WORM Chain Verification:**
- WORM log chain integrity verified by `scripts/verify-nft.sh` (Test 6) via `php artisan imdc:verify-worm`.
- Verifier uses same canonicalizer (`WormHasher::compute()`) as writer for deterministic verification.
- Writer uses same ordering as verifier (`created_at ASC, id ASC`) for tail-based `prev_hash` calculation.
- Guardrail ensures distinct `occurred_at` seconds between mint and transfer (1-second sleep) to prevent ordering ambiguity.
- Verification PASS status confirmed: hash mismatches and prev_hash mismatches resolved.
- All WORM logs in chain verify correctly with canonical hash computation.

**NFT Guardrail (`scripts/verify-nft.sh`):**
- Deterministic flag detection: Reads `FEATURE_NFT` from `.env` file (not shell env).
- Only SKIPs if `FEATURE_NFT` is explicitly `false/0/no/off` in original `.env`.
- If `FEATURE_NFT` is missing from `.env`, proceeds and temporarily adds it.
- Follows same deterministic pattern as `verify-did.sh`:
  - Backs up `.env` to `.env.bak.verify-nft.<timestamp>`.
  - Temporarily sets `FEATURE_NFT=true` in `.env` for guardrail run.
  - Clears caches (config/cache/route) after `.env` modification.
  - Verifies `FEATURE_NFT` is enabled after cache clear.
  - Restores original `.env` on exit (via trap, even on failure).
- When `FEATURE_NFT=true`: Runs full test suite with real operations (no mocks):
  - Test 1: Mint authentication token (admin user)
  - Test 2: Mint NFT token (real database write)
  - Test 3: Create second user for transfer test
  - Test 4: Transfer NFT token (real database write, with 1-second sleep before transfer to ensure distinct WORM timestamps)
  - Test 5: Test idempotency (repeat transfer with same Idempotency-Key, verifies same transfer ID returned)
  - Test 6: Verify WORM log chain integrity (via `php artisan imdc:verify-worm`) — PASS
  - Test 7: Verify WORM logs count
- Guardrail clean mode: Resets nfts database via `migrate:fresh` when `IMDC_NFT_GUARDRAIL_CLEAN=1` for deterministic testing.
- All tests PASS when `FEATURE_NFT=true`, including WORM chain verification with deterministic ordering.
- verify-nft deterministic PASS confirmed: mint, transfer, idempotency, and WORM chain verification all pass.

**OpenAPI Documentation:**
- `docs/openapi/nfts.yaml` created with full API specification.
- Includes all endpoints, request/response schemas, security requirements, and error responses.

---

## Implementation Notes

- All guardrails are production-ready, deterministic, and schema-aware where applicable.
- Feature flags (FEATURE_NFT, FEATURE_DID, FEATURE_LINKING) control guardrail execution but do not affect core functionality when disabled.
- Helper scripts under `scripts/_guardrail/` provide reusable guardrail utilities (ensure_guardrail_product.php, cleanup_guardrail_product.php).
- NFT module is production-ready, fully wired but disabled by default (`FEATURE_NFT=false`).
- NFT endpoints follow API contract: `{ success, data|error, trace_id }`.
- NFT primary keys use UUID format.
- NFT idempotency uses both application-level (IdempotencyKey model) and database-level (unique constraints) enforcement.
- WORM canonicalization is single source of truth: `WormHasher::compute()` used by both writer and verifier.
- WORM hash computation is deterministic: delimiter-joined string format, normalized types, ISO8601 timestamps.
- WORM chain verification PASS: all logs verify correctly with canonical hash computation.

**Docker Compose Configuration:**
- Canonical compose file path: `backend/infra/docker/docker-compose.yml` (relative to project root).
- Guardrail scripts (executed from backend directory) use: `infra/docker/docker-compose.yml` (relative to backend root).
- Documentation and instructions reference the canonical path from project root: `backend/infra/docker/docker-compose.yml`.
- This ensures consistent behavior across all scripts and documentation.

---

## Verification Commands

```bash
# Marketplace guardrail (always runs, auto-seeds product if needed)
./scripts/verify-marketplace.sh

# DID guardrail (always runs, temporarily enables FEATURE_DID)
./scripts/verify-did.sh

# NFT guardrail (skips if FEATURE_NFT=false in .env, otherwise temporarily enables)
./scripts/verify-nft.sh

# RBAC guardrail (schema-aware, handles missing guard_name column)
./scripts/verify-rbac.sh

# Lock guardrail (runs all enabled guardrails)
./scripts/verify-lock.sh
```

---
