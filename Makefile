SHELL := /bin/bash
.ONESHELL:
.PHONY: smoke-token smoke-marketplace

smoke-token:
	cd "$(CURDIR)"
	bash ./scripts/smoke/token.sh

smoke-marketplace:
	cd "$(CURDIR)"
	bash ./scripts/smoke/marketplace.sh
