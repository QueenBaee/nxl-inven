# Barcode Hardware Integration & Manual UAT Protocol

This document provides testing specifications for validating USB and Bluetooth handheld barcode scanners on physical cashier workstations.

---

## 1. Scanner Technology & Operating Mode

- **Scanner Interface**: USB HID Keyboard Wedge or Bluetooth SPP/HID.
- **Data Protocol**: Alphanumeric barcode characters terminated by an automatic `[CR]` / `[Enter]` suffix.
- **No Proprietary SDK**: Operates entirely via standard browser keyboard input events.

---

## 2. Automated Test Coverage vs Physical Hardware Verification

| Test Case | Automated Pest Test Status | Physical Hardware Manual Verification |
| :--- | :---: | :---: |
| **Exact SKU match adds product to cart** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **Unknown barcode triggers alert** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **Zero stock prevents addition** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **Active opname freeze blocks addition** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **Rapid repeated scanning (10x)** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **Autofocus restoration after scan** | ✅ Automated Verified | 📋 Physical Hardware Manual UAT Required |
| **Web Audio API chime / buzz synthesis** | Browser-dependent | 📋 Physical Hardware Manual UAT Required |
| **Cashier tablet Bluetooth scanner latency** | N/A (Hardware) | 📋 Physical Hardware Manual UAT Required |

---

## 3. Physical Hardware UAT Step-by-Step Procedure

1. **Connect Scanner**:
   - Plug USB scanner into cashier PC or pair Bluetooth scanner with cashier tablet.
2. **Open POS Interface**:
   - Navigate to `/admin/pos-page`.
   - Verify the "Scanner Barcode Kasir" input box is automatically focused upon page load.
3. **Scan Valid Product**:
   - Point scanner at product barcode (e.g. `REG-CHALK-01` or EAN-13).
   - Press scanner trigger.
   - **Verification**:
     - Product is instantly added to cart.
     - Web Audio chime plays.
     - Input field clears and remains focused for the next scan.
4. **Rapid Consecutive Scans**:
   - Scan the same product 5 times rapidly.
   - **Verification**: Cart quantity increases to `5` with zero missed keystrokes or UI desynchronization.
5. **Scan Deficit / Frozen Product**:
   - Scan a barcode for a product with `0` stock or frozen by an active opname.
   - **Verification**: Error buzzer sounds, red warning banner appears, and cart quantity remains unchanged.
6. **Concurrent Keyboard & Touch Usability**:
   - Type in the manual product search box, click a touch product card, and then scan a barcode.
   - **Verification**: Manual catalog search and barcode scanning operate seamlessly in parallel.

