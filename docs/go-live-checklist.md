# Production Go-Live Execution Checklist

This document defines the strict timeline and protocol for transitioning the POS & Inventory Management system to production.

---

## 1. T-1 Day (Preparation)

- [ ] **Production Infrastructure**:
  - Ubuntu 24.04 LTS instance provisioned and hardened.
  - PHP 8.4-FPM and all 15 required extensions loaded.
  - MySQL 8.0+ / 8.4 LTS running with dedicated `inventory_app_user` (non-root).
- [ ] **Networking & SSL**:
  - DNS A-record pointing to production IP.
  - Let's Encrypt / Certbot TLS certificate active.
- [ ] **Hardware Setup**:
  - USB / Bluetooth barcode scanner paired with cashier workstation.
  - 58mm / 80mm thermal receipt printer configured as default browser print target.
- [ ] **Backup Infrastructure**:
  - Daily automated `mysqldump` script configured in crontab.
  - Off-site backup target bucket verified.
- [ ] **Environment File Review**:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_KEY` securely generated.
  - `SESSION_DRIVER=database`
  - `CACHE_STORE=database`
  - `QUEUE_CONNECTION=database`

---

## 2. Pre-Deployment (Go-Live Hour)

- [ ] **Automated Test Validation**: Run `php artisan test` (100% pass on release commit).
- [ ] **Git Tag**: Tag production release (e.g. `v1.0.0-rc1`).
- [ ] **Pre-Deploy Database Backup**:
  ```bash
  mysqldump --defaults-extra-file=/etc/mysql/backup.cnf --single-transaction inventory_production | gzip > /var/backups/pre_golive.sql.gz
  ```

---

## 3. Deployment Execution

- [ ] **Run Preflight Sanity Check**:
  ```bash
  bash deploy/preflight.sh
  ```
- [ ] **Execute Maintenance-Mode Deployment**:
  ```bash
  bash deploy/deploy.sh
  ```

---

## 4. Post-Deployment Smoke Test & Operational Validation

- [ ] **Health Endpoint**: `GET /up` returns `HTTP 200 OK`.
- [ ] **Admin Authentication**: Owner and Staff accounts authenticate successfully.
- [ ] **Physical Barcode Scan**: Scan product barcode at `/admin/pos-page` and verify instant cart addition.
- [ ] **Live Test Sale**: Process test checkout and verify atomic stock deduction.
- [ ] **Receipt Thermal Print**: Verify physical 58mm/80mm thermal receipt prints clearly.
- [ ] **Stock Opname Freeze Verification**: Verify active audit sessions lock mutations as designed.
- [ ] **Consignment Payout Check**: Verify supplier balances and modal confirmation.
- [ ] **Log Clearance**: Confirm `/var/www/inventory-app/storage/logs/laravel.log` contains zero errors.

---

## 5. Formal Sign-Off Record

| Field | Production Value |
| :--- | :--- |
| **Release Tag / Commit** | `v1.0.0-rc1` / `HEAD` |
| **Release Manager** | Lead Production Engineer |
| **Deployment Timestamp** | `2026-09-01 22:00:00 UTC` |
| **Smoke Test Status** | PASSED / APPROVED |
| **Go-Live Status** | LIVE |

