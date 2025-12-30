#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "[1/2] Drop+Create databases..."

docker exec -it backend-core-db-1 bash -lc "psql -U postgres -d postgres -c \"DROP DATABASE IF EXISTS imdc_core WITH (FORCE);\""
docker exec -it backend-core-db-1 bash -lc "psql -U postgres -d postgres -c \"CREATE DATABASE imdc_core;\""

docker exec -it backend-products-db-1 bash -lc "psql -U postgres -d postgres -c \"DROP DATABASE IF EXISTS imdc_products WITH (FORCE);\""
docker exec -it backend-products-db-1 bash -lc "psql -U postgres -d postgres -c \"CREATE DATABASE imdc_products;\""

docker exec -it backend-orders-db-1 bash -lc "psql -U postgres -d postgres -c \"DROP DATABASE IF EXISTS imdc_orders WITH (FORCE);\""
docker exec -it backend-orders-db-1 bash -lc "psql -U postgres -d postgres -c \"CREATE DATABASE imdc_orders;\""

docker exec -it backend-inventory-db-1 bash -lc "psql -U postgres -d postgres -c \"DROP DATABASE IF EXISTS imdc_inventory WITH (FORCE);\""
docker exec -it backend-inventory-db-1 bash -lc "psql -U postgres -d postgres -c \"CREATE DATABASE imdc_inventory;\""

echo "[2/2] Running migrations..."
cd "$ROOT_DIR"
bash "$SCRIPT_DIR/migrate-all.sh"

echo "Done."
