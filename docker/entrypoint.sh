#!/usr/bin/env sh
set -e

# Move to project root
cd /var/www/html

echo "[entrypoint] Installing Composer dependencies (no-dev in prod)..."
if [ "$APP_ENV" = "prod" ]; then
  composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader
else
  composer install --prefer-dist --no-progress --no-interaction
fi

echo "[entrypoint] Running database migrations (if any)..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

echo "[entrypoint] Warming up cache..."
php bin/console cache:warmup || true

echo "[entrypoint] Fixing permissions for var/"
chown -R www-data:www-data var || true

exec "$@"


