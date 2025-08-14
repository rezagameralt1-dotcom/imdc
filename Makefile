.PHONY: up down build key perms migrate seed release

up:
\tdocker compose up -d --build

down:
\tdocker compose down

build:
\tcomposer install || true
\tnpm install || true
\tnpm run build || true

key:
\tdocker compose exec app php artisan key:generate

migrate:
\tdocker compose exec app php artisan migrate --force

seed:
\tdocker compose exec app php artisan db:seed --force

release:
\tbash scripts/release_zip.sh
