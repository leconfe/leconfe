#!/usr/bin/env bash
set -euo pipefail

: "${STAGING_PR:?STAGING_PR is required}"
: "${STAGING_IP:?STAGING_IP is required}"

echo "=== Setting up staging for PR #${STAGING_PR} ==="

# Clean up stale env
rm -f .env

echo "Compose file: docker-compose.staging.yml"
echo "APP_URL: http://leconfe-staging-${STAGING_PR}-${STAGING_IP}.traefik.me"

# Start DB first
echo "Starting database..."
STAGING_PR="${STAGING_PR}" STAGING_IP="${STAGING_IP}" docker compose -f docker-compose.staging.yml up -d leconfe-db

# Wait for DB to be healthy
echo "Waiting for database to be healthy..."
for i in $(seq 1 30); do
    db_container="leconfe-staging-db-${STAGING_PR}"
    if docker inspect --format='{{.State.Health.Status}}' "$db_container" 2>/dev/null | grep -q healthy; then
        break
    fi
    sleep 2
done

# Run composer install
echo "Running composer install..."
composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs

# Run composer install for plugins that have their own vendor autoloader
echo "Installing plugin dependencies..."
find plugins -maxdepth 2 -name composer.json -not -path "*/vendor/*" | sort | while read -r plugin_composer; do
    plugin_dir=$(dirname "$plugin_composer")
    if [ -f "$plugin_dir/composer.lock" ]; then
        echo "  Installing dependencies for $plugin_dir ..."
        (cd "$plugin_dir" && composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs 2>&1 | sed 's/^/    /') || echo "  Warning: composer install failed for $plugin_dir, continuing..."
    fi
done

# Fix permissions
echo "Fixing permissions..."
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Start app - entrypoint handles artisan commands
echo "Starting application..."
STAGING_PR="${STAGING_PR}" STAGING_IP="${STAGING_IP}" docker compose -f docker-compose.staging.yml up -d leconfe-staging

echo ""
echo "=== Setup complete ==="
echo "Staging URL: http://leconfe-staging-${STAGING_PR}-${STAGING_IP}.traefik.me"

# Verify
echo "Checking container status..."
docker compose -f docker-compose.staging.yml ps

echo ""
echo "NOTE: It may take a minute for composer install and quick-setup to complete."
echo "Check logs with: docker logs leconfe-staging-${STAGING_PR}"
