# IMDC E2E Expire Reservation Test

## Purpose
Runs a full CLI flow to create a user, place an order, reserve inventory, force the order to age, run the `imdc:expire-order-reservations` command, and verify reservations are released.

## Preconditions
- API is running and reachable (default `http://127.0.0.1:8000`).
- Databases are up (orders, inventory).
- Product with ID (default `019b6cbe-ceb7-7171-a8ee-7567683f73fd`) exists and is active.

## Run (single command)
```bash
cd ~/Desktop/IMDC/backend
bash scripts/imdc_e2e_expire_test.sh
```
Optional env overrides:
```bash
BASE_URL=http://127.0.0.1:8000 \
PRODUCT_ID=your-product-uuid \
TTL_MINUTES=1 \
bash scripts/imdc_e2e_expire_test.sh
```

## What it does
1) Registers a random user, logs in, validates token.
2) Fetches inventory for the product.
3) Creates an order and reserves inventory.
4) Ages the order via tinker (sets created_at 5 minutes back) then runs the expiry command.
5) Prints reservation rows and final inventory state.

## Troubleshooting
- 401 Unauthorized: ensure API is running and Sanctum tokens are accepted; check email/password in the script logs.
- Token extraction failed: login JSON is printed; verify credentials and BASE_URL.
- Missing product/order id: ensure the product exists and is active; inspect /tmp/imdc_order_create.json.
