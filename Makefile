SHELL := /bin/bash
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c

BASE ?= http://localhost
TOKEN := $(shell php scripts/get_token.php | tr -d '\r\n')
PRODUCT_ID ?= $(shell sudo -u postgres psql -At -d imdc -c "select id from products order by id desc limit 1;")
QTY ?= 1
UNIT_PRICE ?= 99000
REASON ?=
TARGET ?=

.PHONY: help preflight smoke setinv restock test deploy

help:
	@echo "Targets:"
	@echo "  make preflight"
	@echo "  make smoke [PRODUCT_ID=.. QTY=.. UNIT_PRICE=..]"
	@echo "  make setinv PRODUCT_ID=.. TARGET=.."
	@echo "  make restock PRODUCT_ID=.. QTY=.. [REASON=..]"
	@echo "  make test    (نیاز به dev deps)"
	@echo "  make deploy  (دیپلوی استاندارد)"

preflight:
	bash scripts/preflight.sh

smoke:
	BASE="$(BASE)" TOKEN="$(TOKEN)" PRODUCT_ID="$(PRODUCT_ID)" QTY="$(QTY)" UNIT_PRICE="$(UNIT_PRICE)" bash scripts/market_smoke.sh

setinv:
	@[ -n "$(TARGET)" ] || { echo "Usage: make setinv PRODUCT_ID=.. TARGET=.."; exit 1; }
	BASE="$(BASE)" TOKEN="$(TOKEN)" bash scripts/inventory_set.sh "$(PRODUCT_ID)" "$(TARGET)"

restock:
	@[ -n "$(QTY)" ] || { echo "Usage: make restock PRODUCT_ID=.. QTY=.. [REASON=..]"; exit 1; }
	BASE="$(BASE)" TOKEN="$(TOKEN)" bash scripts/inventory_restock.sh "$(PRODUCT_ID)" "$(QTY)" "$(REASON)"

test:
	@if [ -x vendor/bin/phpunit ]; then php artisan test -q; else echo "Dev deps not installed; run: composer install"; exit 1; fi

deploy:
	bash scripts/deploy_prod.sh
