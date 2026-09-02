# User Acceptance Testing (UAT) Manual Scenarios

This document outlines human-readable step-by-step User Acceptance Testing (UAT) test procedures for validating the POS & Inventory Monolith in staging and production environments.

---

## Scenario A — Regular Product Inbound & Retail POS Sale

### Objective
Verify that a regular inventory item can be registered, restocked via Inbound, and sold through the POS with atomic stock deduction.

### Steps
1. Navigate to **Inventory > Products** and click **New Product**.
   - SKU: `REG-CHALK-01`
   - Name: `Taom Pyro Chalk 9-Ball`
   - Type: `Regular`
   - Cost Price: `Rp 250.000`
   - Selling Price: `Rp 350.000`
   - Save the record. Initial stock displays `0`.
2. Navigate to **Inventory > Inbound Stock** and click **New Inbound Stock Movement**.
   - Product: `Taom Pyro Chalk 9-Ball`
   - Quantity: `10`
   - Reference Note: `Batch Restock #101`
   - Submit.
3. Return to **Products** and verify `Taom Pyro Chalk` stock is now `10`.
4. Open the **POS Checkout** interface (`/pos` or Livewire component).
   - Add `2` units of `Taom Pyro Chalk 9-Ball` to the cart.
   - Channel: `Offline / In-Store`
   - Payment Method: `QRIS`
   - Click **Process Checkout**.

### Expected Results
- Transaction created with invoice format `INV-{Ymd}-0001` and total `Rp 700.000,00`.
- Product stock immediately updates from `10` to `8`.
- A `StockMovement` of type `out` is recorded with `transaction_id` linked to the invoice.

---

## Scenario B — Insufficient Stock Protection (Zero Partial Transactions)

### Objective
Verify that an oversized POS sale is completely blocked and does not leave partial records.

### Steps
1. In **POS Checkout**, select a product with available stock of `2` units.
2. Attempt to add or checkout with quantity `5`.
3. Click **Process Checkout**.

### Expected Results
- Checkout fails immediately with user-facing message: *"Insufficient stock for [Product Name]. Available: 2, requested: 5."*
- No transaction record or invoice number is created.
- Product stock remains strictly `2`.
- Cart items remain preserved for quantity adjustment.

---

## Scenario C — Consignment Product Lifecycle & Supplier Payout

### Objective
Verify that a consignment product creates an automatic settlement upon sale and allows grouped batch payouts for the supplier.

### Steps
1. Navigate to **Inventory > Suppliers** and create supplier: `Fury Cue Indonesia` (Phone: `081234567890`).
2. Navigate to **Inventory > Products** and create:
   - SKU: `CON-CUE-01`
   - Name: `Fury Carbon Cue 12.5mm`
   - Type: `Consignment`
   - Supplier: `Fury Cue Indonesia`
   - Cost Price: `Rp 2.000.000` (Amount owed to supplier per sold unit)
   - Selling Price: `Rp 2.800.000`
3. Record Inbound stock of `5` units for `Fury Carbon Cue 12.5mm`.
4. Open POS and checkout `1` unit for `Fury Carbon Cue 12.5mm` via Cash.
5. Navigate to **Inventory > Consignment Settlements**.
   - Verify an unpaid settlement exists: Supplier: `Fury Cue Indonesia`, Product: `Fury Carbon Cue 12.5mm`, Amount: `Rp 2.000.000`, Status: `Unpaid`.
6. Click the header action **Payout Supplier**.
   - Select Supplier: `Fury Cue Indonesia`.
   - Outstanding balance calculates: `1 unpaid item(s) | Total: Rp 2.000.000,00`.
   - Submit payout.

### Expected Results
- Settlement status updates from `Unpaid` to `Paid`.
- A daily sequential payout reference is assigned: `PAYOUT-{supplier_id}-{Ymd}-0001`.
- `paid_at` timestamp is set to the current time.
- `PayoutExecuted` domain event is safely dispatched after transaction commit.

---

## Scenario D — Stock Opname Audit (No Variance / Perfect Match)

### Objective
Verify that a physical audit with 100% stock accuracy completes without generating unnecessary adjustment movements.

### Steps
1. Navigate to **Inventory > Stock Opname** and click **New Stock Opname Session**.
   - Session Name: `Weekly Perfect Count Audit`
   - Save session (Status: `Draft`).
2. Click **Start Opname** action on the session row.
   - Confirm the dialog. Status transitions to `In Progress (Active Audit)`.
3. Open the session edit view and scroll to **Audit Items (Blind Count)** relation manager.
4. Enter physical counts exactly matching system stock (e.g. system `8` -> enter `8`).
5. Return to the list view and click **Approve & Sync Stock**.

### Expected Results
- Session status transitions to `Completed (Reconciled)`.
- Zero `adjustment` StockMovements are generated because variance is `0`.
- Financial shop loss and supplier liability are `Rp 0,00`.

---

## Scenario E — Stock Opname Shortage Reconciled to Physical Count

### Objective
Verify that physical inventory shortages overwrite product stock directly and record clear adjustment movements.

### Steps
1. Create and Start a new Stock Opname session `Month-End Shortage Reconcile`.
   - Product A has system stock `20` and cost price `Rp 50.000`.
2. As the auditor, enter physical count `17` (shortage of `3` units).
3. Click **Approve & Sync Stock**.

### Expected Results
- Product A stock becomes strictly `17` (matching physical count).
- An adjustment `StockMovement` is recorded: `quantity = 3`, `type = adjustment`, `stock_opname_item_id = [item_id]`, `reference_note = "Stock Opname Adjustment - Month-End Shortage Reconcile"`.
- Event `StockOpnameApproved` carries total shop loss of `Rp 150.000,00`.

---

## Scenario F — Consignment Shortage Financial Liability Separation

### Objective
Verify that shortages on consignment items are classified as `supplier_liability` rather than `shop_loss`.

### Steps
1. Create and Start a Stock Opname session containing:
   - Regular Product: System `10`, Cost `Rp 20.000`, Physical Count `8` (Deficit 2).
   - Consignment Product: System `5`, Cost `Rp 100.000`, Physical Count `4` (Deficit 1).
2. Review the session as Owner.

### Expected Results
- Regular Product displays `Shop Loss: Rp 40.000,00` (Red badge).
- Consignment Product displays `Supplier Liability: Rp 100.000,00` (Yellow/Warning badge).
- Approving dispatches `StockOpnameApproved` with `totalShopLoss = 40000.00` and `totalSupplierLiability = 100000.00`.

---

## Scenario G — Blind Count Staff Security & Policy Enforcement

### Objective
Verify that staff users cannot view or inspect system counts, variances, or financial loss metrics through UI or browser network inspection.

### Steps
1. Log in with a user account assigned the `staff` role.
2. Navigate to an active `In Progress` Stock Opname session.
3. Inspect the table and open browser DevTools Network / Elements tabs.

### Expected Results
- Table columns only show `Product`, `SKU`, and editable `Physical Qty`.
- `System Qty`, `Variance`, and `Loss / Liability Impact` columns are omitted.
- The underlying Eloquent SQL query projection strictly selects `['id', 'stock_opname_id', 'product_id', 'physical_qty', 'created_at']`, preventing sensitive data from transmitting over the wire.
- Staff attempts to click **Start Opname** or **Approve Opname** are rejected by server-side policy authorization.

---

## Scenario H — High Concurrency & Double-Click Idempotency

### Objective
Verify system resilience against concurrent submissions and rapid double-clicking on checkout and audit actions.

### Steps
1. Open two browser windows with the same POS cart or same Stock Opname session.
2. Simultaneously submit **Process Checkout** on both windows for the last unit of an item in stock.
3. Simultaneously submit **Start Opname** or **Approve Opname** on the same session.

### Expected Results
- Exactly ONE checkout succeeds; the second receives a clean `InsufficientStockException` rollback.
- Exactly ONE opname start/approval executes under pessimistic lock (`lockForUpdate()`); the concurrent request fails gracefully without duplicate records or SQL constraint crashes.

