# Production Database Backup & Disaster Recovery Guide

This document details automated database backup routines, point-in-time snapshot strategies, and disaster recovery procedures for MySQL 8 on the POS & Inventory Management system.

---

## 1. Backup Philosophy & Principles

1. **Transaction-Consistent Snapshots**: All backups must execute with `--single-transaction` to ensure zero table locking during cashier sales while capturing an atomic snapshot.
2. **Pre-Migration Snapshot Rule**: A full backup must be triggered immediately prior to running `php artisan migrate --force`.
3. **Multi-Tier Retention Policy**:
   - Hourly transaction logs / binlogs: Retained for 7 days.
   - Daily full dumps: Retained for 30 days locally and offsite.
   - Monthly archive snapshots: Retained for 12 months.

---

## 2. Automated Daily Backup Script

Create `/usr/local/bin/backup-pos-db.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="/var/backups/mysql"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_NAME="inventory_production"
DB_USER="inventory_app_user"
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_${TIMESTAMP}.sql.gz"

mkdir -p "${BACKUP_DIR}"

# Execute single-transaction dump using MySQL credentials file (.my.cnf)
mysqldump \
    --defaults-extra-file=/etc/mysql/backup.cnf \
    --user="${DB_USER}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    "${DB_NAME}" | gzip -9 > "${BACKUP_FILE}"

# Set restricted permissions
chmod 600 "${BACKUP_FILE}"

# Rotate local backups older than 30 days
find "${BACKUP_DIR}" -type f -name "${DB_NAME}_*.sql.gz" -mtime +30 -delete

echo "Backup created successfully: ${BACKUP_FILE}"
```

Add cron schedule (`crontab -e`):
```crontab
0 2 * * * /usr/local/bin/backup-pos-db.sh >> /var/log/db-backup.log 2>&1
```

---

## 3. Database Restoration & Recovery Procedure

### 3.1 Pre-Restoration Verification
1. Place application in maintenance mode:
   ```bash
   php artisan down --message="System maintenance in progress. Restoring database."
   ```
2. Verify integrity of the compressed backup archive:
   ```bash
   gzip -t /var/backups/mysql/inventory_production_YYYYMMDD_HHMMSS.sql.gz
   ```

### 3.2 Execute Restore Command
```bash
gunzip < /var/backups/mysql/inventory_production_YYYYMMDD_HHMMSS.sql.gz | mysql \
    --defaults-extra-file=/etc/mysql/backup.cnf \
    --user="inventory_app_user" \
    inventory_production
```

### 3.3 Post-Restoration Validation
1. Clear application caches:
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   ```
2. Check database status and transaction logs:
   ```bash
   php artisan migrate:status
   ```
3. Bring application back online:
   ```bash
   php artisan up
   ```

