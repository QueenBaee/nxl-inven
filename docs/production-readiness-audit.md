# Phase 5 — Production Readiness, Stock Ledger Integrity & System Hardening Audit Report

This report presents the findings of the final production readiness audit for the Monolith POS & Inventory Management System across Phases 1 through 4.

---

## 1. Executive Summary

| Severity | Count | Primary Impact Area |
| :--- | :---: | :--- |
| **Critical** | 0 | None (Zero stock-drift or data-corruption paths identified after Phase 4 hardening) |
| **High** | 3 | Filament Authorization on Payouts, Missing Model Policies, Outbound Movement Foreign Key Traceability |
| **Medium** | 3 | Decimal arithmetic consistency in Cart checkout, PayoutExecuted Event After-Commit semantics, Missing Livewire View |
| **Low** | 2 | Soft deletion guard documentation, Missing composite database indexes on high-cardinality filters |

---

## 2. Stock Ledger Integrity Matrix

All inventory mutations in the application strictly flow through `StockMovement` records processed by `StockMovementObserver`. Direct mutations on `products.stock` outside of `StockMovement` are forbidden by domain architecture.

| Operation / Source | DB Record Created | Stock Mutation Effect | Transaction Isolation | Row Lock | Authorization Guard | Observer Enforced |
| :--- | :--- | :--- | :---: | :---: | :---: | :---: |
| **Inbound Restock** (`InboundResource`) | `StockMovement` (`type = in`) | `Product.stock += qty` (atomic increment) | Yes | N/A | Authenticated User | Yes (`StockMovementObserver`) |
| **POS Checkout Sale** (`PosCart`) | `StockMovement` (`type = out`) | `Product.stock -= qty` (atomic decrement) | `DB::transaction(..., 5)` | `lockForUpdate()` on `Product` | Cashier / Authenticated | Yes (`StockMovementObserver`) |
| **Stock Opname Adjustment** (`ApproveStockOpnameAction`) | `StockMovement` (`type = adjustment`) | `Product.stock = physical_qty` (direct sync) | `DB::transaction(..., 5)` | `lockForUpdate()` on `Product` & `StockOpname` | Owner Only (`StockOpnamePolicy`) | Yes (`StockMovementObserver`) |
| **Active Audit Freeze Guard** | Attempted `in` or `out` | Mutation **REJECTED** with `StockOpnameInProgressException` | Transaction Rolled Back | `lockForUpdate()` | Observer Level | Yes (`StockMovementObserver::creating`) |

---

## 3. Filament Authorization Matrix

| Resource / Entity | Role: Owner | Role: Staff | View List / Detail | Create | Update | Delete | Special Domain Actions |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| **`ProductResource`** | Full Access | Read-Only | All | Owner | Owner | Owner (Guarded: No movements) | None |
| **`SupplierResource`** | Full Access | Read-Only | All | Owner | Owner | Owner (Guarded: No products/settlements) | None |
| **`InboundResource`** | Full Access | Full Access | All | Staff/Owner | Forbidden | Forbidden | Stock Movement Recorded |
| **`ConsignmentSettlementResource`** | Full Access | Read-Only | All | Observer Only | Observer Only | Forbidden | `payoutSupplier` (Owner Only), `markAsPaid` (Owner Only) |
| **`StockOpnameResource`** | Full Access | Count-Only | All | Owner | Owner (Draft) | Owner (Draft Only) | `startOpname` (Owner Only), `approveOpname` (Owner Only) |
| **`StockOpnameItem`** | Full Access | Blind Count | Scoped | System Only | Staff/Owner (Count) | Forbidden | `viewAuditDetails` (Owner Only) |

---

## 4. Detailed Audit Findings & Action Items

### Finding 1: [HIGH] Missing Authorization Guards on Consignment Payout Actions
- **ID**: `SEC-001`
- **File(s)**: `app/Filament/Resources/ConsignmentSettlementResource.php`, `app/Policies/`
- **Current Behavior**:
  The `payoutSupplier` header action and `markAsPaid` bulk action lack explicit server-side authorization checks (`authorize(...)`). There is also no `ConsignmentSettlementPolicy`.
- **Reproduction Condition**:
  A staff member navigating to the Filament admin panel could trigger a supplier payout batch or mark settlements as paid directly via Livewire payload submission.
- **Risk**: Unauthorized disbursement execution or tampering with supplier settlement statuses by non-owner roles.
- **Recommended Fix**:
  Create `ConsignmentSettlementPolicy` and attach `->authorize(fn () => auth()->user()?->hasRole('owner'))` to both the `payoutSupplier` and `markAsPaid` actions.

---

### Finding 2: [HIGH] Missing Foreign Key Traceability on Outbound POS Stock Movements
- **ID**: `DAT-001`
- **File(s)**: `database/migrations/`, `app/Models/StockMovement.php`, `app/Livewire/PosCart.php`
- **Current Behavior**:
  Outbound sales movements created by `PosCart` store `'reference_note' => "Sale {$transaction->invoice_number}"`. There is no relational foreign key linking the `stock_movements` record to the `transactions` table.
- **Reproduction Condition**:
  During historical data auditing or reconciliation, joins between stock deductions and master transaction records rely on string pattern parsing rather than an indexed relational foreign key.
- **Risk**: Slower ledger audit queries and fragility if invoice number formats are adjusted in the future.
- **Recommended Fix**:
  Add `transaction_id` foreign key (`nullable`, constrained to `transactions`, `cascadeOnUpdate`, `nullOnDelete`) on the `stock_movements` table and populate it in `PosCart::checkout()`.

---

### Finding 3: [HIGH] Missing Model Policies for Products, Suppliers, and Stock Movements
- **ID**: `SEC-002`
- **File(s)**: `app/Policies/`
- **Current Behavior**:
  `Product`, `Supplier`, and `StockMovement` models do not have dedicated Laravel policies registered, defaulting authorization to permissive built-in Filament behavior.
- **Reproduction Condition**:
  Staff users could access edit and deletion screens for products and suppliers if URLs are directly traversed.
- **Risk**: Staff modifying supplier details, changing product cost/selling prices, or attempting to delete active master data.
- **Recommended Fix**:
  Implement `ProductPolicy`, `SupplierPolicy`, and `StockMovementPolicy` enforcing owner-only permissions for creation, updates, and restricted deletions.

---

### Finding 4: [MEDIUM] Floating-Point Arithmetic in POS Cart Total Computation
- **ID**: `FIN-001`
- **File(s)**: `app/Livewire/PosCart.php`
- **Current Behavior**:
  `PosCart::checkout()` computes `$itemSubtotal = $sellingPrice * $requestedQuantity;` and `$totalAmount += $itemSubtotal;` using native PHP floats before persisting `total_amount`.
- **Reproduction Condition**:
  High-volume checkouts with decimal prices (e.g. fractional rates or tax rates) could accumulate IEEE 754 floating-point inaccuracies.
- **Risk**: Minor sub-cent financial rounding drift between computed transaction totals and line items.
- **Recommended Fix**:
  Use `bcmul()` and `bcadd()` string arithmetic for `total_amount` calculation in `PosCart::checkout()`.

---

### Finding 5: [MEDIUM] `PayoutExecuted` Event Lacks After-Commit Interface
- **ID**: `EVT-001`
- **File(s)**: `app/Events/PayoutExecuted.php`
- **Current Behavior**:
  `PayoutExecuted` is dispatched inside the `DB::transaction()` block in `ConsignmentSettlementResource` but does not implement `ShouldDispatchAfterCommit`.
- **Reproduction Condition**:
  If a database error occurs after dispatch or if a listener throws an unhandled exception, listeners could execute against an uncommitted payout.
- **Risk**: Premature execution of downstream payout listeners (such as PDF generation or external banking disbursement webhooks).
- **Recommended Fix**:
  Implement `Illuminate\Contracts\Events\ShouldDispatchAfterCommit` on `PayoutExecuted`.

---

### Finding 6: [MEDIUM] Missing Livewire Blade View `pos-cart.blade.php`
- **ID**: `UX-001`
- **File(s)**: `resources/views/livewire/pos-cart.blade.php`
- **Current Behavior**:
  `PosCart::render()` calls `view('livewire.pos-cart')`, but the blade template file does not exist in `resources/views/livewire/`.
- **Reproduction Condition**:
  Attempting to render the Livewire component in a web page returns a `ViewNotFoundException`.
- **Risk**: Broken user interface when accessing the POS frontend.
- **Recommended Fix**:
  Create `resources/views/livewire/pos-cart.blade.php` with item listing, payment method selector, sales channel selector, feedback alerts, and checkout triggers.

---

### Finding 7: [LOW] Missing Composite Index for Supplier Settlement Queries
- **ID**: `PERF-001`
- **File(s)**: `database/migrations/`
- **Current Behavior**:
  The `consignment_settlements` table has an index on `payout_reference`, but lacks a composite index on `['supplier_id', 'status']`.
- **Reproduction Condition**:
  With 100,000+ settlements, queries filtering unpaid settlements for a specific supplier will require table scans.
- **Risk**: Minor performance degradation on large-scale supplier payout calculations.
- **Recommended Fix**:
  Add composite index `['supplier_id', 'status']` on `consignment_settlements`.

