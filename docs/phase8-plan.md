# Phase 8 — POS Hardware Readiness & Production Deployment Plan

This document outlines the implementation strategy for cashier hardware integration (USB/Bluetooth barcode scanners and thermal receipt printers) and production deployment architecture.

---

## 1. Barcode Strategy & Schema Decision

### Schema Decision
- **Authoritative Identifier**: The `products.sku` column is a unique, indexed string specifically designed as the primary product code.
- **Scanner Integration**: Standard USB/Bluetooth handheld scanners operate as **keyboard-wedge** devices, broadcasting alphanumeric characters followed by an `[Enter]` keystroke.
- **Decision**: Use `products.sku` as the barcode identifier. No redundant column is required.
- **Matching Rules**:
  1. Exact SKU match (e.g., `REG-CHALK-01` or EAN-13 `8991234567890`).
  2. Case-insensitive lookup.
  3. Single-scan auto-add to cart with immediate autofocus recovery.

### Scanner Workflow & Feedback
- Scanner input is isolated from the debounced catalog search to eliminate latency during rapid scanning.
- Rapid scan protection: Sequential scans increment item quantities atomically without UI desynchronization.
- Audio/Visual feedback: Instant green visual confirmation on success, red alert on error, and Web Audio API synthesized audio beeps (800Hz beep for success, 300Hz double-buzz for failure) requiring zero external audio assets.

---

## 2. Receipt & Printing Architecture

### Receipt Route & Authorization
- **Endpoint**: `GET /admin/transactions/{transaction}/receipt` (`name('receipt.show')`).
- **Controller**: `App\Http\Controllers\ReceiptController@show`.
- **Security**: Protected by `auth` middleware and server-side policy check (`can('view', $transaction)`).
- **Immutability Guarantee**: Read-only presentation template; printing does not create database records, stock movements, or transactions.

### Thermal Printer Styling (58mm / 80mm)
- `@media print` CSS targeting standard POS thermal paper widths (`max-width: 80mm`, fallback `58mm`).
- Monospaced typography, high-contrast monochrome layout, zero margin wastage, and page-break isolation.

---

## 3. Production Deployment Architecture

### Server & Runtime Assumptions
- **Operating System**: Ubuntu 24.04 LTS (Noble Numbat).
- **Web Server**: Nginx 1.26+ with HTTP/2 and TLS 1.3.
- **PHP Version**: PHP 8.4 with PHP-FPM (`bcmath`, `pdo_mysql`, `mbstring`, `intl`, `xml`, `curl`, `zip`, `opcache`).
- **Database**: MySQL 8.0+ / 8.4 LTS with InnoDB engine.
- **Process Manager**: Systemd / Supervisor for queue workers.

### Artifacts to Create
- `deploy/nginx/pos.conf` — Nginx virtual host configuration.
- `deploy/supervisor/pos-worker.conf` — Supervisor background queue worker daemon.
- `deploy/deploy.sh` — Zero-downtime deployment script (`set -euo pipefail`).
- `docs/backup-and-recovery.md` — MySQL backup and disaster recovery guide.
- `docs/release-checklist.md` — Pre-deploy, deploy, and post-deploy operational checklist.
- `docs/production-smoke-test.md` — 16-point post-deployment smoke test procedure.

