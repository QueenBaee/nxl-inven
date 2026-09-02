# Phase 6 — Operational Dashboard, Reports & Analytics Documentation

This document serves as the authoritative operational manual and technical specification for the reporting and analytics layer of the POS & Inventory Management monolith.

---

## 1. Executive Summary & Architecture

Phase 6 introduces a high-performance business intelligence and operational reporting layer built natively on top of the production transaction ledger (Phases 1–5).

### Key Architectural Tenets:
1. **Zero Double-Counting / Drift**: All financial figures derive directly from verified database columns (`transactions.total_amount`, `transaction_items.price`, `products.cost_price`, `stock_movements.quantity`) using SQL aggregations and BCMath precision.
2. **Strict Ownership Separation**: Owned working capital is strictly decoupled from supplier-owned consignment inventory.
3. **Role-Based Server-Side Guarding**: Commercial financial metrics (gross sales, inventory cost valuations, supplier liabilities, and sales trends) are enforced server-side for the `owner` role.

---

## 2. Dashboard Widgets & KPI Specifications

| Widget Name | Component Class | Target Role | Key Formula / SQL Source |
| :--- | :--- | :---: | :--- |
| **Sales Today** | `OwnerStatsOverviewWidget` | Owner | `SUM(total_amount)` for `status = 'completed'` AND `DATE(created_at) = CURRENT_DATE()` |
| **Transactions Today** | `OwnerStatsOverviewWidget` | Owner | `COUNT(id)` for completed transactions today |
| **Items Sold Today** | `OwnerStatsOverviewWidget` | Owner | `SUM(transaction_items.quantity)` for completed transactions today |
| **Gross Sales This Month** | `OwnerStatsOverviewWidget` | Owner | `SUM(total_amount)` where `created_at >= startOfMonth()` |
| **Owned Inventory Cost Valuation** | `OwnerStatsOverviewWidget` | Owner | `SUM(stock * cost_price)` WHERE `products.type = 'regular'` |
| **Consignment Stock Valuation** | `OwnerStatsOverviewWidget` | Owner | `SUM(stock * cost_price)` WHERE `products.type = 'consignment'` (Supplier-owned) |
| **Pending Consignment Payable** | `OwnerStatsOverviewWidget` | Owner | `SUM(amount)` for `consignment_settlements.status = 'unpaid'` |
| **Low Stock Products Count** | `OwnerStatsOverviewWidget` | Owner | `COUNT(id)` WHERE `products.stock <= 5` |
| **Sales Trend Chart** | `SalesTrendChartWidget` | Owner | Grouped by `DATE(created_at)` over 7, 30, or 90 days with continuous timeline |
| **Sales by Sales Channel** | `SalesByChannelChartWidget` | Owner | Doughnut chart grouped by `transactions.channel` (`offline`, `shopee`, `tokopedia`, etc.) |
| **Sales by Payment Method** | `SalesByPaymentMethodChartWidget` | Owner | Bar chart grouped by `payment_method` (`cash`, `qris`, `transfer`) |
| **Top Selling Products (30 Days)** | `TopSellingProductsWidget` | Owner | Ranked by `SUM(transaction_items.quantity)` DESC (Top 10 items) |
| **Low Stock Replenishment Alert** | `LowStockAlertWidget` | Owner & Staff | Real-time table of products with `stock <= 5` for operational restocking |
| **Recent Stock Movements** | `RecentStockMovementsWidget` | Owner & Staff | Last 10 inventory mutations with traceable source links |

---

## 3. Dedicated Operational Reports

### 3.1 Daily Sales & Transaction Report (`/admin/daily-sales-report`)
- **Purpose**: Comprehensive daily and historical transaction audit explorer.
- **Filters**: Date range (`from_date`, `to_date`), Sales Channel, Payment Method.
- **Columns**: Invoice Number, Timestamp, Sales Channel badge, Payment Method badge, Total Items Count, Gross Sales (Rp), Cashier Name.
- **Access Rule**: Owner Only (`canAccess()`).

### 3.2 Inventory Cost Valuation Report (`/admin/inventory-valuation-report`)
- **Purpose**: Balance sheet working capital estimation and supplier custody auditing.
- **Owned Inventory Semantics**: Evaluates store-owned merchandise (`ProductType::Regular`). Represents actual capital invested by the shop owner.
- **Consignment Inventory Semantics**: Evaluates supplier-owned merchandise (`ProductType::Consignment`). Held on consignment; excluded from store-owned capital.
- **Formula**: `stock * cost_price` computed via BCMath.
- **Access Rule**: Owner Only.

### 3.3 Stock Movement & Ledger Audit Report (`/admin/stock-movement-report`)
- **Purpose**: Full relational traceability on every stock mutation.
- **Traceable Sources**:
  - `Sale: INV-20260901-0001` (Direct foreign key to POS Transaction).
  - `Opname: Month-End Audit` (Direct foreign key to Stock Opname Item).
  - `Direct Inbound / Restock` (Manual receiving note).
- **Filters**: Movement Type (`in`, `out`, `adjustment`), Product, Date Range.
- **Access Rule**: Owner Only.

### 3.4 Slow & Non-Moving Inventory Report (`/admin/slow-moving-products-report`)
- **Purpose**: Identify stagnant working capital for vendor return or promotional clearance.
- **Filters**: No sales in last 30 days, 60 days, 90 days, or Never Sold (`whereDoesntHave('transactionItems')`).
- **Columns**: SKU, Product Name, Ownership Type, Current Stock, Unit Cost, Capital Tied Up, Last Sold Date.
- **Access Rule**: Owner Only.

### 3.5 Consignment Supplier Exposure Report (`/admin/consignment-exposure-report`)
- **Purpose**: Consolidated supplier dashboard tracking retail performance, paid payouts, and pending payable balances.
- **Columns**: Supplier Name, Contact Person, Active SKUs, Units Sold, Total Cost Generated, Amount Settled (Paid), Outstanding Balance (Unpaid).
- **Informational Opname Section**: Displays *Opname Shortage Liability (Informational)* calculated from approved audit discrepancies without conflating it with retail payable settlements.
- **Access Rule**: Owner Only.

---

## 4. Date, Timezone & Precision Standards

1. **Timezone**: All daily queries (`whereDate('created_at', today())`) strictly utilize the application's configured timezone (`config('app.timezone')`).
2. **Money Precision**:
   - Aggregations are calculated using database `DECIMAL(15,2)` sums or PHP string BCMath (`bcmul`, `bcadd`).
   - `number_format()` is applied exclusively at the final Blade template presentation boundary.
3. **Database Performance Indexing**:
   - `transactions`: Indexed on `['status', 'created_at']`.
   - `transaction_items`: Indexed on `['created_at', 'product_id']`.
   - `stock_movements`: Indexed on `['type', 'created_at']` and `['product_id', 'created_at']`.
   - `products`: Indexed on `['type', 'stock']`.
   - `consignment_settlements`: Indexed on `['supplier_id', 'status']`.

