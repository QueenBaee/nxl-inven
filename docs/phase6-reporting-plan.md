# Phase 6 — Dashboard, Reporting & Operational Analytics Design Plan

This document defines the architectural plan for the operational reporting and business intelligence layer of the POS & Inventory Management monolith.

---

## 1. Overview & Non-Goals

### Objectives
- Provide real-time, actionable operational visibility for store owners and cashiers.
- Answer critical daily operational questions (sales, low stock, inventory cost valuation, consignment exposure, sales velocity, movement audit trails).
- Maintain 100% financial and mathematical precision using SQL aggregation and BCMath.
- Enforce strict server-side role-based authorization: sensitive commercial and financial metrics (costs, profits, valuations, supplier liabilities) are accessible exclusively by users with the `owner` role.

### Explicit Non-Goals (Out of Scope for Phase 6)
- No General Ledger, double-entry journal entries, or chart of accounts.
- No balance sheet or tax accounting systems.
- No automated settlement creation from Stock Opname shortages (kept strictly informational).
- No external BI or heavy third-party reporting frameworks.

---

## 2. Dashboard Widgets Specification

### 2.1 Owner KPI Stats Overview (`OwnerStatsOverviewWidget`)
- **Visibility**: `owner` role only (`canView()`).
- **Cards**:
  1. **Sales Today**: `Transaction::where('status', Completed)->whereDate('created_at', today())->sum('total_amount')`
  2. **Transactions Today**: `Transaction::where('status', Completed)->whereDate('created_at', today())->count()`
  3. **Items Sold Today**: `TransactionItem::whereHas('transaction', fn ($q) => $q->where('status', Completed)->whereDate('created_at', today()))->sum('quantity')`
  4. **Gross Sales This Month**: `Transaction::where('status', Completed)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total_amount')`
  5. **Owned Inventory Cost Value**: `Product::where('type', Regular)->selectRaw('SUM(stock * cost_price) as val')->value('val')`
  6. **Consignment Inventory Value (Supplier-Owned)**: `Product::where('type', Consignment)->selectRaw('SUM(stock * cost_price) as val')->value('val')`
  7. **Pending Consignment Settlement**: `ConsignmentSettlement::where('status', Unpaid)->sum('amount')`
  8. **Low Stock Count**: `Product::where('stock', '<=', 5)->count()`

### 2.2 Sales Trend Chart (`SalesTrendChartWidget`)
- **Visibility**: `owner` role only.
- **Filters**: 7 Days, 30 Days (Default), 90 Days.
- **Query**: Grouped SQL aggregation on `DATE(created_at)` calculating `SUM(total_amount)` and `COUNT(id)`.
- **Display**: Multi-line / Bar chart showing Gross Sales (Rp) and Transaction count per date.

### 2.3 Sales Distribution Charts
- **Sales by Sales Channel (`SalesByChannelChartWidget`)**:
  - Aggregation on `channel` for completed transactions in the selected period.
- **Sales by Payment Method (`SalesByPaymentMethodChartWidget`)**:
  - Aggregation on `payment_method` (Cash, QRIS, Bank Transfer).

### 2.4 Top-Selling Products (`TopSellingProductsWidget`)
- **Visibility**: `owner` role only.
- **Query**: Aggregated join between `products` and `transaction_items` constrained to completed transactions in the last 30 days.
- **Columns**: Rank, Product Name, SKU, Total Units Sold, Gross Revenue Generated.
- **Limit**: Top 10 items.

### 2.5 Operational Alerts (Shared Owner & Staff)
- **Low Stock Alerts (`LowStockAlertWidget`)**:
  - Table of products with `stock <= 5` for immediate replenishment action.
- **Recent Stock Movements (`RecentStockMovementsWidget`)**:
  - Table of the 10 most recent stock mutations with traceable source references.

---

## 3. Dedicated Operational Reports Specification

### 3.1 Daily Sales & Transaction Report (`DailySalesReport`)
- **Navigation**: `Reports & Analytics > Sales Report` (Owner only).
- **Filters**: Date range (`from_date`, `to_date`), `channel`, `payment_method`.
- **Header KPI Summary**: Total Transactions, Total Units Sold, Total Gross Sales.
- **Table**: Invoice Number, Date/Time, Sales Channel, Payment Method, Item Count, Total Amount, Cashier.
- **Export**: CSV Export capability.

### 3.2 Inventory Valuation Report (`InventoryValuationReport`)
- **Navigation**: `Reports & Analytics > Inventory Valuation` (Owner only).
- **Semantics**:
  - **Owned Inventory**: Shop-owned products where inventory cost represents active store working capital.
  - **Consignment Inventory**: Supplier-owned products held in custody; tracked separately from shop capital.
- **Table Columns**: SKU, Product Name, Product Type (`Regular` vs `Consignment`), Supplier, Stock Qty, Cost Price, Total Valuation (`stock * cost_price`).
- **Summary Cards**: Total Owned Inventory Capital (Rp), Total Consignment Stock Value (Rp), Overall Physical Units.
- **Export**: CSV Export capability.

### 3.3 Stock Movement Audit Report (`StockMovementReport`)
- **Navigation**: `Reports & Analytics > Stock Movements` (Owner only).
- **Filters**: Date Range, Movement Type (`in`, `out`, `adjustment`), Product.
- **Columns**: Date/Time, Product, SKU, Type, Quantity, Traceable Source (`Transaction: INV-xxx`, `Opname: Session Name`, `Inbound: Note`), Recorded By.
- **Export**: CSV Export capability.

### 3.4 Slow & Non-Moving Products Report (`SlowMovingProductsReport`)
- **Navigation**: `Reports & Analytics > Slow / Non-Moving` (Owner only).
- **Filters**: Inactivity Period (No sales in last 30 days, 60 days, 90 days, or Never Sold).
- **Columns**: SKU, Product Name, Type, Current Stock, Unit Cost, Capital Tied Up, Last Sale Date.
- **Purpose**: Identify stagnant stock for promotional clearance or vendor return.
- **Export**: CSV Export capability.

### 3.5 Consignment Supplier Exposure Report (`ConsignmentExposureReport`)
- **Navigation**: `Reports & Analytics > Consignment Summary` (Owner only).
- **Grouping**: Grouped per Supplier.
- **Metrics**: Active Consignment SKUs, Total Units Sold, Total Amount Generated, Total Amount Paid/Settled, Total Outstanding Unpaid Payable.
- **Informational Section**: *Opname Supplier Liability (Informational)* displaying historical opname loss exposure without conflating it with payable settlements.
- **Export**: CSV Export capability.

### 3.6 Sales Transaction Explorer (`TransactionResource`)
- **Navigation**: `Sales > Transactions` (Owner: full details; Staff: receipt/lookup).
- **Capability**: View past sales invoices, customer receipts, and line item breakdowns.

---

## 4. Query Performance & Database Indexing Plan

To maintain high throughput on 100,000+ transactions and 1,000,000+ movements:
- `transactions`: Composite index on `['status', 'created_at']` for date-range and monthly sales queries.
- `transaction_items`: Composite index on `['created_at', 'product_id']` for top-selling and velocity calculations.
- `stock_movements`: Composite index on `['type', 'created_at']` and `['product_id', 'created_at']`.
- `products`: Index on `['type', 'stock']` for inventory valuation queries.

---

## 5. Security & Role Authorization Matrix

| Component / Page | Owner | Staff | Enforcement Mechanism |
| :--- | :---: | :---: | :--- |
| **Owner Stats KPI Cards** | Visible | Hidden / Denied | `canView()` check `auth()->user()->hasRole('owner')` |
| **Sales Trend & Distribution Charts** | Visible | Hidden / Denied | `canView()` check `auth()->user()->hasRole('owner')` |
| **Top Selling Products Widget** | Visible | Hidden / Denied | `canView()` check `auth()->user()->hasRole('owner')` |
| **Low Stock Alert Widget** | Visible | Visible | Operational stock replenishment visibility |
| **Recent Movements Widget** | Visible | Visible | Traceability on inventory mutations |
| **Daily Sales Report Page** | Access | 403 Forbidden | `canAccess()` check in Page class |
| **Inventory Valuation Report Page** | Access | 403 Forbidden | `canAccess()` check in Page class |
| **Stock Movement Report Page** | Access | 403 Forbidden | `canAccess()` check in Page class |
| **Slow-Moving Products Report Page** | Access | 403 Forbidden | `canAccess()` check in Page class |
| **Consignment Summary Report Page** | Access | 403 Forbidden | `canAccess()` check in Page class |
| **Transactions Explorer Resource** | Full Access | Read / Lookup | Governed by `TransactionPolicy` |

