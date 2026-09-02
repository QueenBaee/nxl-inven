#!/usr/bin/env bash
# ==============================================================================
# Maintenance-Mode Deployment Script for POS & Inventory Monolith
# Operating System: Ubuntu 24.04 LTS / PHP 8.4-FPM / MySQL 8
# ==============================================================================

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/inventory-app}"
PHP_BIN="${PHP_BIN:-php}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

# Trap on unexpected failure to guide administrator recovery
cleanup_on_failure() {
    echo ""
    echo "❌ ======================================================================="
    echo "❌ DEPLOYMENT FAILED AT LINE $1"
    echo "❌ ======================================================================="
    echo "⚠️ The application may currently be in MAINTENANCE MODE."
    echo "👉 To bring the application back online after resolving the issue, run:"
    echo "     ${PHP_BIN} artisan up"
    echo "👉 To check detailed error logs, inspect:"
    echo "     ${APP_DIR}/storage/logs/laravel.log"
    echo "❌ ======================================================================="
    exit 1
}

trap 'cleanup_on_failure $LINENO' ERR

echo ">>> [1/7] Navigating to application directory: ${APP_DIR}"
cd "${APP_DIR}"

echo ">>> [2/7] Running pre-deployment preflight check"
bash deploy/preflight.sh

echo ">>> [3/7] Activating Laravel Maintenance Mode"
${PHP_BIN} artisan down --retry=60 || true

echo ">>> [4/7] Pulling latest repository release"
git fetch origin main
git reset --hard origin/main

echo ">>> [5/7] Installing PHP dependencies & Building front-end assets"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
if command -v npm >/dev/null 2>&1; then
    npm ci --prefer-offline --no-audit
    npm run build
fi

echo ">>> [6/7] Running database migrations safely"
${PHP_BIN} artisan migrate --force --no-interaction

echo ">>> [7/7] Caching Laravel configurations, routes, and Filament components"
${PHP_BIN} artisan config:cache
${PHP_BIN} artisan route:cache
${PHP_BIN} artisan view:cache
${PHP_BIN} artisan event:cache
${PHP_BIN} artisan icons:cache
${PHP_BIN} artisan filament:cache-components

# Secure file permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Reload PHP-FPM to flush OPcache
if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload "${PHP_FPM_SERVICE}" || true
fi

echo ">>> Disabling Maintenance Mode"
${PHP_BIN} artisan up

echo "=============================================================================="
echo "✅ Maintenance-mode deployment completed successfully! Application is live."
echo "=============================================================================="
