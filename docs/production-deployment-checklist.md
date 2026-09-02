# Production Deployment & Operational Readiness Checklist

This document provides operational specifications, deployment procedures, environment configuration guidelines, and disaster recovery strategies for deploying the POS & Inventory Management monolith to production.

---

## 1. Environment & Infrastructure Prerequisites

- **PHP Version**: PHP 8.4+ (required for Laravel 13 & Filament v3).
- **PHP Extensions**: `bcmath`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`.
- **Database**: MySQL 8.0+ (Default InnoDB engine, `utf8mb4_unicode_ci` charset).
- **Web Server**: Nginx or Caddy with reverse proxy and SSL termination.
- **Process Manager**: Supervisor or Systemd for queue workers and scheduler.

---

## 2. Production `.env` Configuration Checklist

| Parameter | Required Value | Rationale |
| :--- | :--- | :--- |
| `APP_ENV` | `production` | Enables production error handling and security guards. |
| `APP_DEBUG` | `false` | **CRITICAL**: Never expose stack traces or SQL errors in production. |
| `APP_KEY` | `base64:...` | Generate via `php artisan key:generate`. |
| `APP_URL` | `https://your-domain.com` | Must use HTTPS for secure admin and POS sessions. |
| `DB_CONNECTION` | `mysql` | MySQL 8 is the primary database. |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | Secure private network interface. |
| `DB_DATABASE` | `inventory_production` | Dedicated production schema. |
| `SESSION_DRIVER` | `database` or `redis` | Race-safe session storage across multiple cashier nodes. |
| `CACHE_STORE` | `redis` or `database` | High-throughput caching layer. |
| `QUEUE_CONNECTION` | `database` or `redis` | Asynchronous processing for events and future notifications. |

---

## 3. Production Deployment Step-by-Step Procedure

Execute the following commands during deployment pipelines:

```bash
# 1. Enter Maintenance Mode (prevents concurrent checkouts during schema updates)
php artisan down --retry=60

# 2. Pull latest codebase and install production dependencies
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Execute database migrations
php artisan migrate --force --no-interaction

# 4. Cache application configurations, routes, and Filament components
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache
php artisan filament:cache-components

# 5. Restart background queue workers
php artisan queue:restart

# 6. Exit Maintenance Mode
php artisan up
```

---

## 4. Background Workers & Scheduler Configuration

### 4.1 Systemd / Supervisor Queue Worker
Create `/etc/supervisor/conf.d/inventory-worker.conf`:

```ini
[program:inventory-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/inventory-app/artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/inventory-app/storage/logs/worker.log
stopwaitsecs=3600
```

### 4.2 Cron Job for Laravel Scheduler
Add to crontab (`crontab -e -u www-data`):

```crontab
* * * * * cd /var/www/inventory-app && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Backup, Disaster Recovery & Ledger Safety

### 5.1 Automated MySQL Database Backup Schedule
- **Full Database Dump**: Executed daily at 02:00 AM via `mysqldump` with single-transaction consistency:
  ```bash
  mysqldump -u [db_user] -p[db_pass] --single-transaction --quick --routines --triggers inventory_production | gzip > /backups/inventory_db_$(date +\%Y\%m\%d_\%H\%M\%S).sql.gz
  ```
- **Backup Retention**:
  - Daily backups: Retained for 30 days.
  - Monthly snapshots: Retained for 12 months.
  - Off-site Replication: Encrypted sync to Amazon S3 or Cloudflare R2 bucket.

### 5.2 Pre-Migration Backup Rule
**MANDATORY**: Before running any future schema migrations on the production database, an explicit snapshot must be captured:
```bash
php artisan backup:run --only-db # or manual mysqldump
```

### 5.3 Restore Verification Test Procedure
- Test restoring backup archives quarterly to an isolated staging database server.
- Verify checksums and run test transaction queries to confirm data integrity.

