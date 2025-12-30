#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
fi

run_psql() {
  local host="$1" port="$2" user="$3" dbname="$4" sql="$5"
  PGPASSWORD="${6:-}" psql -h "$host" -p "$port" -U "$user" -d postgres -c "$sql"
}

drop_and_create() {
  local name="$1" host="$2" port="$3" user="$4" password="$5" database="$6"
  echo "[db-reset] dropping $database..."
  run_psql "$host" "$port" "$user" "$database" "DROP DATABASE IF EXISTS \"$database\" WITH (FORCE);" "$password"
  echo "[db-reset] creating $database..."
  run_psql "$host" "$port" "$user" "$database" "CREATE DATABASE \"$database\";" "$password"
}

reset_module() {
  local module="$1" host="$2" port="$3" user="$4" password="$5" database="$6" path="$7"
  drop_and_create "$module" "$host" "$port" "$user" "$password" "$database"
  if [[ -d "$path" ]]; then
    php artisan migrate --database="$module" --path="$path" --force
  else
    php artisan migrate --database="$module" --force
  fi
}

cd "$ROOT_DIR"

reset_module "core" \
  "${DB_CORE_HOST:-${DB_HOST:-127.0.0.1}}" \
  "${DB_CORE_PORT:-${DB_PORT:-5432}}" \
  "${DB_CORE_USERNAME:-${DB_USERNAME:-postgres}}" \
  "${DB_CORE_PASSWORD:-${DB_PASSWORD:-}}" \
  "${DB_CORE_DATABASE:-imdc_core}" \
  "database/migrations/core"

reset_module "products" \
  "${DB_PRODUCTS_HOST:-${DB_HOST:-127.0.0.1}}" \
  "${DB_PRODUCTS_PORT:-${DB_PORT:-5432}}" \
  "${DB_PRODUCTS_USERNAME:-${DB_USERNAME:-postgres}}" \
  "${DB_PRODUCTS_PASSWORD:-${DB_PASSWORD:-}}" \
  "${DB_PRODUCTS_DATABASE:-imdc_products}" \
  "database/migrations/products"

reset_module "orders" \
  "${DB_ORDERS_HOST:-${DB_HOST:-127.0.0.1}}" \
  "${DB_ORDERS_PORT:-${DB_PORT:-5432}}" \
  "${DB_ORDERS_USERNAME:-${DB_USERNAME:-postgres}}" \
  "${DB_ORDERS_PASSWORD:-${DB_PASSWORD:-}}" \
  "${DB_ORDERS_DATABASE:-imdc_orders}" \
  "database/migrations/orders"

reset_module "inventory" \
  "${DB_INVENTORY_HOST:-${DB_HOST:-127.0.0.1}}" \
  "${DB_INVENTORY_PORT:-${DB_PORT:-5432}}" \
  "${DB_INVENTORY_USERNAME:-${DB_USERNAME:-postgres}}" \
  "${DB_INVENTORY_PASSWORD:-${DB_PASSWORD:-}}" \
  "${DB_INVENTORY_DATABASE:-imdc_inventory}" \
  "database/migrations/inventory"

echo "[db-reset] complete"
