# Phase 4 Stock Opname — Security, Concurrency, and Correctness Audit Report

This document records the findings of the exhaustive code and architecture audit of **Phase 4: Stock Opname (Blind Count)** within the monolith inventory system.

---

## Summary of Findings

| Severity | Count | Key Area |
| :--- | :---: | :--- |
| **Critical** | 2 | Non-deterministic Adjustment Stock Sync, Concurrency Races in Opname Actions |
| **High** | 3 | Missing Filament Resource Policies, Event Dispatch Before Commit, TOCTOU in Session Transitions |
| **Medium** | 2 | Incomplete Uncounted Product Reporting, Missing Direct Foreign Key Link on Movements |
| **Low** | 1 | Business Logic Embedded in Filament UI Closures |

---

## Detailed Audit Issues

### 1. [CRITICAL] Non-Deterministic Physical Quantity Lookup in StockMovementObserver

- **File**: `app/Observers/StockMovementObserver.php`
- **Current Behavior**: 
  When a `StockMovement` of type `adjustment` is created, the observer attempts to find the target `physical_qty` using:
  ```php
  $physicalQty = StockOpnameItem::where('product_id', $stockMovement->product_id)
      ->whereHas('stockOpname', fn ($q) => $q->whereIn('status', [OpnameStatus::InProgress, OpnameStatus::Completed]))
      ->latest('id')
      ->value('physical_qty');
  ```
- **Why It Is Wrong**:
  Relying on `latest('id')` and ambient database state is inherently non-deterministic. If multiple completed opname sessions exist in history, or if items are queried across sessions, `latest('id')` can select an item from a prior historical audit rather than the specific opname session that generated the adjustment.
- **Risk**: Stock corruption during reconciliation, setting inventory levels to stale or incorrect historical physical counts.
- **Proposed Fix**:
  Add a nullable `stock_opname_item_id` foreign key on the `stock_movements` table. When creating an adjustment movement during approval, explicitly set `stock_opname_item_id = $item->id`. In `StockMovementObserver`, resolve `physical_qty` directly through the explicit relation: `$stockMovement->stockOpnameItem->physical_qty`.

---

### 2. [CRITICAL] TOCTOU Race Condition & Duplicate Item Creation on "Start Opname"

- **File**: `app/Filament/Resources/StockOpnameResource.php`
- **Current Behavior**:
  The check for existing in-progress sessions (`StockOpname::where('status', OpnameStatus::InProgress)->exists()`) runs *outside* the database transaction and without a pessimistic row lock (`lockForUpdate()`). Furthermore, the draft `StockOpname` record is not locked before inserting items.
- **Why It Is Wrong**:
  Under concurrent HTTP requests or rapid double-clicks:
  1. Two draft sessions can both pass the outside-transaction `exists()` check, enter `DB::transaction()`, snapshot products, and transition to `in_progress`, violating the strict rule that only ONE `in_progress` session may exist globally.
  2. Double-clicking on the same session causes concurrent item insertions, triggering unhandled MySQL unique constraint violations (`QueryException` 1062 on `[stock_opname_id, product_id]`) and returning a 500 error.
- **Risk**: Multiple active opname sessions running simultaneously, freeze-guard confusion, and unhandled 500 crashes during session start.
- **Proposed Fix**:
  Encapsulate the start logic in `StartStockOpnameAction` wrapped in `DB::transaction(..., 5)`. Acquire a pessimistic lock on the target session (`lockForUpdate()`), verify it is currently `draft`, atomically lock and check for existing `in_progress` sessions, lock products during snapshot creation, and update status atomically.

---

### 3. [HIGH] Race Condition, Double Approval, and Missing Row Locks on "Approve Opname"

- **File**: `app/Filament/Resources/StockOpnameResource.php`
- **Current Behavior**:
  The uncounted items check (`$record->items()->whereNull('physical_qty')->count()`) is performed outside the transaction without row locking. The session and item rows are updated without pessimistic concurrency protection.
- **Why It Is Wrong**:
  If two managers or browser sessions submit approval simultaneously:
  1. Both requests pass the pre-check.
  2. Both loop through items and create duplicate `StockMovement` adjustment records.
  3. Both dispatch the `StockOpnameApproved` event, causing duplicate downstream notifications, logs, and potential future accounting double-entries.
- **Risk**: Duplicate adjustment movements created, duplicate event dispatch, and corrupted historical audit trails.
- **Proposed Fix**:
  Encapsulate the approval logic in `ApproveStockOpnameAction` wrapped in `DB::transaction(..., 5)`. Lock the session row with `lockForUpdate()`, verify `status === OpnameStatus::InProgress`, lock all related item rows with `lockForUpdate()`, validate count completeness, create adjustment movements, update status to `completed`, and dispatch events safely.

---

### 4. [HIGH] Missing Filament Model & Action Authorization (Security & Blind-Count Protection)

- **File**: `app/Filament/Resources/StockOpnameResource.php`, `app/Policies/`
- **Current Behavior**:
  There is no `StockOpnamePolicy`. The "Start Opname" and "Approve Opname" actions rely solely on `->visible()`, which only hides buttons in the UI template.
- **Why It Is Wrong**:
  In Filament / Livewire, `visible()` does not prevent a non-owner staff user from crafting or invoking the underlying Livewire action component method. Staff members could initiate session starts, approve inventory reconciliations, or modify session records.
- **Risk**: Unauthorized staff initiating audits, bypassing blind-count protocols, or approving inventory write-offs without owner authorization.
- **Proposed Fix**:
  Create `StockOpnamePolicy` enforcing `viewAny`, `view`, `create`, `update`, `delete`, `start`, and `approve` permissions for the `'owner'` role (with staff restricted to view/count within active sessions). Add explicit authorization guards on Filament actions.

---

### 5. [HIGH] Event Dispatch Timing & Transaction Rollback Vulnerability

- **File**: `app/Events/StockOpnameApproved.php`, `app/Filament/Resources/StockOpnameResource.php`
- **Current Behavior**:
  `StockOpnameApproved::dispatch(...)` is invoked inside the `DB::transaction()` block, and the event class does not implement `ShouldDispatchAfterCommit`.
- **Why It Is Wrong**:
  If synchronous event listeners execute and fail, or if a database deadlock/commit failure occurs after dispatch, the event has already triggered listeners despite the transaction rolling back.
- **Risk**: Downstream listeners (e.g., consignment liability generators, email notifications, loss ledgers) executing on aborted transactions.
- **Proposed Fix**:
  Implement `Illuminate\Contracts\Events\ShouldDispatchAfterCommit` on `StockOpnameApproved` and use `DB::afterCommit(...)` when triggering post-transaction workflows.

---

### 6. [MEDIUM] Missing Descriptive Product Names for Uncounted Blocking Errors

- **File**: `app/Filament/Resources/StockOpnameResource.php`
- **Current Behavior**:
  When approval is blocked due to uncounted items, the notification only states:
  `"Cannot complete opname: X item(s) have not been counted yet."`
- **Why It Is Wrong**:
  Rule 6 explicitly requires: *"Show useful information about which products are still uncounted."*
- **Risk**: Poor operator visibility on large catalogs (1,000+ products), forcing staff to manually page through large tables to find missing counts.
- **Proposed Fix**:
  Collect the names and SKUs of the uncounted products (e.g., up to 5 items listed by name + "and X more") in the validation exception and Filament error notification.

---

### 7. [LOW] Monolithic Business Logic in Filament UI Closures

- **File**: `app/Filament/Resources/StockOpnameResource.php`
- **Current Behavior**:
  All complex multi-step database transactions, snapshot loops, and reconciliation mathematics reside inside anonymous closures within the Filament resource table action definitions.
- **Why It Is Wrong**:
  Makes automated testing, CLI reconciliation, and reusability difficult and tightly coupled to the UI framework.
- **Risk**: Reduced maintainability and difficult automated unit testing.
- **Proposed Fix**:
  Extract `StartStockOpnameAction` and `ApproveStockOpnameAction` under `app/Actions/StockOpname/`. Keep Filament resources thin by delegating to these dedicated domain actions.

