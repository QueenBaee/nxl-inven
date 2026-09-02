# Phase 7 — UI/UX & POS Experience Audit Report

This audit assesses the usability, navigation hierarchy, information layout, mobile/tablet responsiveness, and point-of-sale cashier experience of the Monolith POS & Inventory system.

---

## 1. Executive Summary

| Severity | Count | Primary Areas |
| :--- | :---: | :--- |
| **Critical UX** | 1 | POS Lacks Integrated Product Search & Catalog (Cashiers cannot discover/add products without manual inputs) |
| **High** | 4 | POS Missing from Filament Navigation Sidebar, Raw Exception Exposure in UI, Unsegmented Dashboard Grid, Untuned Mobile/Tablet Cart Layout |
| **Medium** | 4 | Inconsistent Number/Currency Formatting, Missing Empty State Illustrations, Weak Loading State Indicators on Checkout/Payouts, Product Form Section Structure |
| **Low** | 2 | Navigation Icon Semantics, Color Palette Semantic Tuning |

---

## 2. Detailed Audit Findings

### Finding 1: [CRITICAL UX] POS Lacks Integrated Product Search & Catalog
- **File(s)**: `app/Livewire/PosCart.php`, `resources/views/livewire/pos-cart.blade.php`
- **Current Behavior**:
  The POS view only renders an empty cart table. There is no product catalog grid or real-time SKU/Name search input to browse items and click-to-add into the cart.
- **Why It Is Wrong**:
  In a physical store or cashier counter, cashiers must search for products by name/SKU and immediately add them to the cart. Without an embedded catalog/search interface, POS operations are blocked.
- **Risk**: Cashiers unable to process sales in the web UI.
- **Proposed Fix**:
  Add an interactive product search and catalog grid on the left/main pane of the POS interface with debounced search, category/type indicators, real-time stock levels, out-of-stock disabling, and one-click item addition.

---

### Finding 2: [HIGH] POS Missing from Filament Sidebar Navigation
- **File(s)**: `app/Filament/Pages/`
- **Current Behavior**:
  The POS cart component is not accessible via a dedicated Filament navigation item in the admin sidebar.
- **Why It Is Wrong**:
  Cashiers and store managers logging into Filament must manually know internal URLs rather than accessing the POS directly from the `Sales` navigation group.
- **Risk**: Poor discoverability and operator friction.
- **Proposed Fix**:
  Create `app/Filament/Pages/PosPage.php` mapped to `Sales > Point of Sale (POS)` with `heroicon-o-calculator` icon.

---

### Finding 3: [HIGH] Unsegmented Dashboard Grid & Visual Clutter
- **File(s)**: `app/Filament/Widgets/*`, `app/Providers/Filament/AdminPanelProvider.php`
- **Current Behavior**:
  KPI stats, charts, leaderboards, and movement tables are rendered with default auto-ordering, causing visual crowding and arbitrary stacking on smaller laptop screens.
- **Why It Is Wrong**:
  Store owners cannot scan critical revenue health and operational alerts in 5 seconds.
- **Proposed Fix**:
  Structure widgets into 5 clear visual tiers:
  1. *Business Velocity*: Today's Sales, Transactions, Units Sold, Monthly Gross.
  2. *Inventory & Liabilities*: Owned Inventory Capital, Consignment Custody Value, Pending Settlements, Low Stock count.
  3. *Sales Analytics*: 30-day Sales Trend chart (2/3 width) + Channel Distribution (1/3 width).
  4. *Leaderboards & Restocking*: Top Selling Products (2/3 width) + Low Stock Replenishment Alerts (1/3 width).
  5. *Audit Trail*: Recent Stock Movements (full width).

---

### Finding 4: [HIGH] Raw Exception Exposure & Non-Localized Error Messages
- **File(s)**: `app/Livewire/PosCart.php`, `resources/views/livewire/pos-cart.blade.php`
- **Current Behavior**:
  When an item is frozen by an active opname or stock is insufficient, English backend exception messages like `Stock mutations are frozen for product ID X` are displayed in the banner.
- **Why It Is Wrong**:
  Cashiers require friendly, actionable feedback in standard Indonesian store terminology (e.g. *"Stok tidak mencukupi"* or *"Produk sedang dikunci karena sesi Stock Opname"*).
- **Risk**: Confused cashiers and delayed customer checkouts.
- **Proposed Fix**:
  Catch domain exceptions in `PosCart` and map them into clear, human-readable operational feedback alerts.

---

### Finding 5: [MEDIUM] Product Form Lacks Semantic Section Grouping
- **File(s)**: `app/Filament/Resources/ProductResource.php`
- **Current Behavior**:
  All product fields are packed into a single section box.
- **Why It Is Wrong**:
  Mixing SKU identity, cost prices, selling prices, stock levels, and supplier links in one flat form slows down product registration and creates visual fatigue.
- **Proposed Fix**:
  Segment product forms into distinct semantic cards: `Product Identity` (SKU, Name, Type), `Pricing & Margins` (Cost Price, Selling Price), `Inventory & Stock` (Current Stock, Safety Alert), and conditional `Consignment Supplier Details`.

---

### Finding 6: [MEDIUM] Missing Loading State Feedback on Checkout and Payouts
- **File(s)**: `resources/views/livewire/pos-cart.blade.php`, `app/Filament/Resources/ConsignmentSettlementResource.php`
- **Current Behavior**:
  Submitting checkout does not visually lock the button or display an animated processing spinner.
- **Why It Is Wrong**:
  Under slow network conditions, cashiers may double-tap the button.
- **Proposed Fix**:
  Add `wire:loading.attr="disabled"` and spinner transitions on checkout and payout triggers.

---

### Finding 7: [MEDIUM] Uninformative Empty States
- **File(s)**: Report pages, transaction tables, movement tables
- **Current Behavior**:
  Empty tables display blank rows without contextual guidance.
- **Why It Is Wrong**:
  Operators cannot distinguish between a loaded empty state and a system query failure.
- **Proposed Fix**:
  Add descriptive empty states with friendly guidance (e.g., *"Belum ada data transaksi pada filter yang dipilih"*).

