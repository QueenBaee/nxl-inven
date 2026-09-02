# Production Release & Deployment Checklist

This document provides an end-to-end verification checklist for launching releases of the POS & Inventory Management system to production.

---

## 1. Pre-Deployment Stage

- [ ] **Automated Test Suite**: Full Pest test suite executes and passes 100%:
  ```bash
  php artisan test
  ```
- [ ] **Code Styling**: Code passes Pint formatting standards:
  ```bash
  vendor/bin/pint --test
  ```
- [ ] **Database Snapshot**: Pre-deployment MySQL dump created and verified:
  ```bash
  mysqldump --defaults-extra-file=/etc/mysql/backup.cnf --single-transaction inventory_production | gzip > /var/backups/pre_deploy.sql.gz
  ```
- [ ] **Environment Audit**:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_KEY` set
  - `SESSION_DRIVER=database` or `redis`
  - `CACHE_STORE=database` or `redis`
  - `QUEUE_CONNECTION=database`
  - `LOG_LEVEL=error` or `info`

---

## 2. Deployment Execution Stage

- [ ] **Enter Maintenance Mode**:
  ```bash
  php artisan down --retry=60
  ```
- [ ] **Install Production Dependencies**:
  ```bash
  composer install --no-dev --optimize-autoloader --no-interaction
  npm ci && npm run build
  ```
- [ ] **Execute Schema Migrations**:
  ```bash
  php artisan migrate --force --no-interaction
  ```
- [ ] **Cache Configurations & Components**:
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
  php artisan icons:cache
  php artisan filament:cache-components
  ```
- [ ] **File Permissions**:
  ```bash
  chown -R www-data:www-data storage bootstrap/cache
  chmod -R 775 storage bootstrap/cache
  ```
- [ ] **Restart Services**:
  ```bash
  sudo systemctl reload php8.4-fpm
  php artisan queue:restart
  ```
- [ ] **Exit Maintenance Mode**:
  ```bash
  php artisan up
  ```

---

## 3. Post-Deployment Verification Stage

- [ ] **Authentication**: Confirm Owner login and Staff login are operational.
- [ ] **Health Endpoint**: Validate `GET /up` returns `HTTP 200 OK`.
- [ ] **POS Checkout**: Perform test sale, verify receipt generation, and confirm stock deduction.
- [ ] **Barcode Scanning**: Verify USB/Bluetooth barcode scanner input adds products correctly.
- [ ] **Stock Opname**: Confirm active sessions freeze checkout as expected.
- [ ] **Log Inspection**: Inspect `/var/www/inventory-app/storage/logs/laravel.log` for zero critical exceptions.

