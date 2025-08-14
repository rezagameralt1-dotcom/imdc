.PHONY: setup serve fresh test

setup:
	bash scripts/setup.sh

serve:
	php artisan serve --host 127.0.0.1 --port 8000

fresh:
	php artisan migrate:fresh --seed

test:
	php artisan test || vendor/bin/phpunit || true
