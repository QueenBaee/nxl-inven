# Staging Server Deployment Guide

This document provides step-by-step procedures for provisioning and deploying the POS & Inventory Monolith to a staging environment (Ubuntu 24.04 LTS / PHP 8.4-FPM / MySQL 8).

---

## 1. Staging Server Provisioning

```bash
# Update Ubuntu package repositories
sudo apt-get update && sudo apt-get upgrade -y

# Install Nginx, Git, Curl, Unzip
sudo apt-get install -y nginx git curl unzip supervisor

# Add Ondřej Surý PHP PPA for PHP 8.4
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update

# Install PHP 8.4-FPM and required extensions
sudo apt-get install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-bcmath \
    php8.4-curl \
    php8.4-dom \
    php8.4-fileinfo \
    php8.4-mbstring \
    php8.4-mysql \
    php8.4-xml \
    php8.4-zip \
    php8.4-opcache

# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js 22 LTS (for Vite asset build)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs
```

---

## 2. MySQL Database Setup

Create dedicated staging database and non-root application user:

```sql
CREATE DATABASE inventory_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'inventory_staging_user'@'localhost' IDENTIFIED BY 'STRONG_STAGING_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, LOCK TABLES ON inventory_staging.* TO 'inventory_staging_user'@'localhost';

FLUSH PRIVILEGES;
```

---

## 3. Clone Repository & Environment Setup

```bash
# Clone to web root
sudo git clone https://github.com/your-org/inventory-app.git /var/www/inventory-app
cd /var/www/inventory-app

# Configure Staging .env
cp .env.example .env
# Edit .env with staging DB credentials, APP_ENV=staging, APP_DEBUG=false, APP_URL=https://staging.your-domain.com

# Run Preflight Verification
bash deploy/preflight.sh
```

---

## 4. Initial Build & Migration

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Build Frontend Assets
npm ci
npm run build

# Generate App Key
php artisan key:generate

# Run Migrations
php artisan migrate --force

# Warm Laravel & Filament Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache
php artisan filament:cache-components

# Set Secure Permissions
sudo chown -R www-data:www-data /var/www/inventory-app/storage /var/www/inventory-app/bootstrap/cache
sudo chmod -R 775 /var/www/inventory-app/storage /var/www/inventory-app/bootstrap/cache
```

---

## 5. Nginx Configuration

Copy `deploy/nginx/pos.conf` to `/etc/nginx/sites-available/inventory-staging.conf`, adjust `server_name` and paths, then enable:

```bash
sudo ln -s /etc/nginx/sites-available/inventory-staging.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 6. Staging Validation Steps

1. Verify `GET /up` returns `HTTP 200 OK`.
2. Login as Owner at `/admin/login`.
3. Login as Staff at `/admin/login`.
4. Perform end-to-end POS sale, barcode scan test, and receipt print verification.

