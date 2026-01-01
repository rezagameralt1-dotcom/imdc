#!/bin/sh
set -e

# Ensure vendor/autoload.php exists (idempotent)
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "vendor/autoload.php not found. Running composer install..."
    cd /var/www/html
    composer install --no-dev --optimize-autoloader --no-interaction
    echo "Composer dependencies installed."
else
    echo "vendor/autoload.php exists. Skipping composer install."
fi

# Execute the original command
exec "$@"
