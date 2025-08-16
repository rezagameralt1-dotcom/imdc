SHELL := /bin/bash

PROJECT_DIR := /var/www/imdc

.PHONY: up down restart logs bash app bash-web key env migrate seed cache-clear build-fe build-release package verify

up:
	docker compose up -d --build

down:
	docker compose down

restart: down up

logs:
	docker compose logs -f --tail=200

bash:
	docker exec -it imdc_app bash || true

bash-web:
	docker exec -it imdc_web sh || true

key:
	docker exec -it imdc_app php artisan key:generate

env:
	@[ -f .env ] || cp .env.example .env && echo ".env prepared"

migrate:
	docker exec -it imdc_app php artisan migrate --force

seed:
	docker exec -it imdc_app php artisan db:seed --force || true

cache-clear:
	docker exec -it imdc_app php artisan optimize:clear

build-fe:
	docker exec -it imdc_app bash -lc "cd frontend && npm ci && npm run build"
	docker exec -it imdc_app bash -lc "rm -rf public/dist && cp -r frontend/dist public/"

build-release:
	@echo "Building production (composer --no-dev & vite build)"
	docker exec -it imdc_app bash -lc "composer install --no-dev --optimize-autoloader"
	$(MAKE) build-fe

package:
	docker exec -it imdc_app bash -lc "cd $(PROJECT_DIR) && zip -r ~/imdc_release.zip . -x 'node_modules/*' -x 'vendor/*' -x 'tests/*' -x 'e2e/*' -x 'storage/logs/*' -x '.git/*' -x '*.bak-*' -x '*.log'"
	@echo "Release created at ~/imdc_release.zip"

verify:
	docker exec -it imdc_app php artisan route:list | grep health || true
	docker exec -it imdc_app php artisan migrate:status
	docker exec -it imdc_app bash -lc "test -d public/dist && echo 'Frontend build present' || echo 'Frontend build MISSING'"
