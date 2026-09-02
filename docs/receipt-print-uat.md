# Thermal Receipt Printing Manual UAT Protocol

This document provides testing specifications for validating 58mm and 80mm POS thermal receipt printing in staging and live cashier environments.

---

## 1. Printing Technology & Scope

- **Print Method**: Native browser print dialog (`window.print()`) rendering standard HTML/CSS.
- **Paper Width Support**: Configurable `@media print` layout supporting both 58mm (compact) and 80mm (standard) POS thermal paper rolls.
- **Monochrome Styling**: High-contrast pure black on white (`#000000` on `#FFFFFF`), no color gradients, and monospaced typography (`Courier New` / `monospace`).
- **No Native Driver Bridge Required**: Operates using any standard Windows/macOS/Linux thermal printer driver configured as a system printer in Chrome, Edge, or Firefox.

---

## 2. Automated Test Coverage vs Physical Hardware Verification

| Test Case | Automated Pest Test Status | Physical Hardware Manual Verification |
| :--- | :---: | :---: |
| **Receipt route authentication & policy** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **Transaction data & totals accuracy** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **Immutability (Zero stock mutations on print)** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **58mm paper roll text wrapping & width** | N/A (Visual) | 📋 Physical Hardware Manual UAT Required |
| **80mm paper roll text formatting** | N/A (Visual) | 📋 Physical Hardware Manual UAT Required |
| **Header / footer alignment & margins** | N/A (Visual) | 📋 Physical Hardware Manual UAT Required |
| **Thermal printer paper tear-off spacing** | N/A (Hardware) | 📋 Physical Hardware Manual UAT Required |

---

## 3. Physical Hardware UAT Step-by-Step Procedure

1. **Printer Setup**:
   - Connect 58mm or 80mm thermal receipt printer via USB or Network.
   - Set as default printer in operating system with paper size set to `58mm x Receipt` or `80mm x Receipt`.
2. **Execute POS Sale**:
   - Open `/admin/pos-page`, add items, and click **Proses Transaksi (Bayar)**.
   - Click the green **Cetak Struk (Receipt)** button on the success banner.
3. **Inspect Print Preview & Print**:
   - Verify browser print preview renders only the receipt box (action buttons and navigation bars hidden).
   - Click Print.
4. **Physical Receipt Inspection**:
   - **Store Header**: Store Name and invoice number clearly readable.
   - **Line Items**: Product name wraps neatly without overlapping prices or quantities.
   - **Totals**: Total amount formatted prominently in Indonesian Rupiah.
   - **Page Length**: Receipt cuts cleanly without excessive blank paper trailing.
5. **Historical Reprint Test**:
   - Navigate to `/admin/transactions`, locate a past transaction, and click **Cetak Struk**.
   - Verify the reprinted receipt matches the original invoice exactly.

