# Phase 7 — UI/UX Refinement & POS Experience Documentation

This document describes the user interface standards, navigation hierarchy, cashier point-of-sale workflow, dashboard layout, and responsive design specifications for the POS & Inventory Management system.

---

## 1. Application Navigation Hierarchy

The Filament sidebar navigation is structured into 5 logical groups:

| Group | Item | Route / Resource | Access | Icon |
| :--- | :--- | :--- | :---: | :--- |
| **Dashboard** | Dashboard | `/admin` | Owner & Staff | `heroicon-o-home` |
| **Sales** | Point of Sale (POS) | `/admin/pos-page` | Owner & Staff | `heroicon-o-calculator` |
| | Transactions | `/admin/transactions` | Owner & Staff | `heroicon-o-shopping-bag` |
| **Inventory** | Products | `/admin/products` | Owner (Full) / Staff (Read) | `heroicon-o-cube` |
| | Inbound Stock | `/admin/inbounds` | Owner & Staff | `heroicon-o-arrow-down-tray` |
| | Stock Opname | `/admin/stock-opnames` | Owner (Approve) / Staff (Count) | `heroicon-o-clipboard-document-check` |
| **Consignment** | Suppliers | `/admin/suppliers` | Owner (Full) / Staff (Read) | `heroicon-o-truck` |
| | Settlements | `/admin/consignment-settlements` | Owner (Payout) / Staff (Read) | `heroicon-o-banknotes` |
| **Reports & Analytics** | Daily Sales Report | `/admin/daily-sales-report` | Owner Only | `heroicon-o-chart-bar` |
| | Inventory Valuation | `/admin/inventory-valuation-report` | Owner Only | `heroicon-o-calculator` |
| | Stock Movement Report | `/admin/stock-movement-report` | Owner Only | `heroicon-o-arrows-right-left` |
| | Slow Moving Products | `/admin/slow-moving-products-report` | Owner Only | `heroicon-o-clock` |
| | Consignment Summary | `/admin/consignment-exposure-report` | Owner Only | `heroicon-o-briefcase` |

---

## 2. Dashboard Layout & Visual Tier Hierarchy

The store owner dashboard (`/admin`) is organized into 5 tiers:

1. **Row 1 — Business Velocity (Top Cards)**:
   - *Sales Today* (Omset hari ini + jumlah transaksi + item terjual)
   - *Gross Sales This Month* (Total omset bulan berjalan)
2. **Row 2 — Working Capital & Payable Health (Financial Cards)**:
   - *Owned Inventory Cost* (Valuasi modal toko - Barang Regular)
   - *Consignment Stock Value* (Nilai barang konsinyasi - Hak milik supplier)
   - *Pending Consignment Payable* (Sisa saldo tagihan konsinyasi belum lunas)
   - *Low Stock Products Count* (Jumlah produk mendekati habis)
3. **Row 3 — Sales Analytics**:
   - Left (2/3 width): *Sales Trend Chart* (Grafik garis omset 7 / 30 / 90 hari)
   - Right (1/3 width): *Sales by Channel Chart* (Distribusi channel offline vs marketplace)
4. **Row 4 — Distribution & Leaderboards**:
   - Left (1/3 width): *Sales by Payment Method Chart* (Tunai, QRIS, Transfer)
   - Right (2/3 width): *Top Selling Products Leaderboard* (10 produk terlaris)
5. **Row 5 — Operational Restocking & Audit**:
   - Left (1/3 width): *Low Stock Replenishment Alert* (Daftar barang sisa <= 5 pcs)
   - Right (2/3 width): *Recent Stock Movements Audit Trail* (Mutasi keluar/masuk terbaru)

---

## 3. High-Velocity POS Cashier Experience

### 3.1 Layout & Component Structure
- **Left Pane (7 cols / 60%)**:
  - Real-time debounced product search (SKU & Name).
  - Quick type filter pills (*Semua*, *Toko / Regular*, *Konsinyasi*).
  - Product catalog cards with stock count, selling price, and out-of-stock badges.
  - Frozen opname visual indicators preventing selection.
- **Right Pane (5 cols / 40%)**:
  - Sticky checkout cart container.
  - Channel dropdown & payment method dropdown.
  - Stepper controls (`+`, `−`, delete) with 44px touch targets.
  - Prominent grand total in Indonesian Rupiah.
  - Process Checkout button with `wire:loading.attr="disabled"` and animated spinner.

---

## 4. Semantic Status Colors & Badge Standards

| Status / Metric | Semantic Color | Context |
| :--- | :---: | :--- |
| **Regular (Owned)** | Gray / Neutral | Shop-owned merchandise |
| **Consignment** | Purple / Violet | Supplier-owned merchandise |
| **Completed / Paid** | Green / Emerald | Settled transaction / Approved opname |
| **Pending / In Progress** | Yellow / Amber | Active audit / Unpaid settlement |
| **Draft / Inactive** | Gray | Draft opname session |
| **Low Stock / Shortage** | Red / Rose | Stock deficit / Physical shortage |
| **Surplus** | Blue / Sky | Physical count higher than system stock |

---

## 5. Responsive Behavior

- **Desktop (1280px+)**: 2-column side-by-side layout (catalog on left, sticky cart on right).
- **Tablet (768px - 1024px)**: 2-column grid with enlarged touch targets for touchscreen terminals.
- **Mobile (<768px)**: Stacked responsive layout with fluid catalog cards and full-width checkout action.

