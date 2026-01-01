#!/bin/bash
set -e

# Create databases for IMDC multi-database architecture
# This script runs on PostgreSQL container initialization
# It's idempotent: databases are only created if they don't exist

# Function to create database if it doesn't exist
create_db_if_not_exists() {
    local dbname=$1
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -tc "SELECT 1 FROM pg_database WHERE datname = '$dbname'" | grep -q 1 || \
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -c "CREATE DATABASE $dbname"
}

# Create all required databases
create_db_if_not_exists imdc_core
create_db_if_not_exists imdc_products
create_db_if_not_exists imdc_orders
create_db_if_not_exists imdc_inventory
create_db_if_not_exists imdc_nfts
