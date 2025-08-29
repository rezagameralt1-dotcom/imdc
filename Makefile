BASE ?= http://localhost
PRODUCT_ID ?= $(shell sudo -u postgres psql -At -d imdc -c "select id from products order by id desc limit 1;")
TOKEN := $(shell php scripts/get_token.php | tr -d '\r\n')
QTY ?= 1
UNIT_PRICE ?= 99000
TARGET ?= 10

.PHONY: smoke preflight restock setinv
smoke:
	BASE="$(BASE)" TOKEN="$(TOKEN)" PRODUCT_ID="$(PRODUCT_ID)" QTY="$(QTY)" UNIT_PRICE="$(UNIT_PRICE)" bash scripts/market_smoke.sh

preflight:
	bash scripts/preflight.sh

restock:
	BASE="$(BASE)" TOKEN="$(TOKEN)" bash scripts/inventory_restock.sh "$(PRODUCT_ID)" "$(QTY)"

setinv:
	BASE="$(BASE)" TOKEN="$(TOKEN)" bash scripts/inventory_set.sh "$(PRODUCT_ID)" "$(TARGET)"
