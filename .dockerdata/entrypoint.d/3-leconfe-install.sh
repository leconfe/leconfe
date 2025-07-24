#!/bin/sh

set -e

# Wait for the DB to be ready
echo "Waiting for database..."
until php artisan migrate:status > /dev/null 2>&1; do
  sleep 3
  echo "Waiting for database connection..."
done

# Run installation if not installed
if ! php artisan migrate:status | grep -q "migrations"; then
  echo "Running Laravel installation..."
  php artisan migrate --force
  php artisan db:seed --force
  php artisan storage:link
  echo "Installation complete!"
fi

# Run supervisor/php-fpm/nginx/etc.
exec "$@"