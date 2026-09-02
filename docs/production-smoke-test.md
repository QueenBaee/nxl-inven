# Post-Deployment Smoke Test Procedure (16-Point Checklist)

This document provides a 16-point manual quality assurance smoke test script to execute immediately after deploying the POS & Inventory application to staging or production.

---

| # | Test Item | Action | Expected Result | Pass/Fail |
| :-: | :--- | :--- | :--- | :-: |
| **1** | **Owner Login** | Login with owner credentials at `/admin/login` | Access granted to full dashboard and reports | [ ] |
| **2** | **Staff Login** | Login with cashier/staff credentials | Access granted to POS, limited to operational tools | [ ] |
| **3** | **Health Endpoint** | Query `GET /up` via curl or browser | Returns HTTP 200 OK | [ ] |
| **4** | **Manual Product Search** | Open POS (`/admin/pos-page`), type product name in search bar | Matching catalog items render instantly with stock badges | [ ] |
| **5** | **Barcode Scanner Input** | Scan a product SKU with USB/Bluetooth scanner + Enter | Product auto-adds to cart with success feedback | [ ] |
| **6** | **Zero Stock Prevention** | Scan/add product with 0 stock | Alert "Stok produk habis" appears; checkout blocked | [ ] |
| **7** | **Opname Freeze Block** | Attempt sale on item currently in active opname audit | Alert "Produk sedang dikunci karena Stock Opname aktif" | [ ] |
| **8** | **POS Checkout** | Select Channel & Payment, click "Proses Transaksi" | Invoice generated (`INV-{Ymd}-XXXX`) with success banner | [ ] |
| **9** | **Stock Deduction Audit** | Check product stock in `/admin/products` | Stock decreased exactly by checkout quantity | [ ] |
| **10** | **Stock Movement Record** | Check `/admin/stock-movements` | `out` movement recorded with foreign key `transaction_id` | [ ] |
| **11** | **Consignment Settlement** | If consignment item sold, check `/admin/consignment-settlements` | Unpaid settlement record created for supplier | [ ] |
| **12** | **Receipt Printing** | Click "Cetak Struk" on transaction row or POS success banner | Clean 58mm/80mm printable thermal receipt opens | [ ] |
| **13** | **Reprint Immutability** | Reprint historical receipt multiple times | Zero stock movements created; transaction remains untouched | [ ] |
| **14** | **Dashboard Accuracy** | Check `/admin` as Owner | Sales Today, Transactions Today, and Inventory values match | [ ] |
| **15** | **Staff Authorization** | Attempt access to `/admin/daily-sales-report` as Staff | HTTP 403 Forbidden / Access Denied | [ ] |
| **16** | **Error Log Inspection** | Review `storage/logs/laravel.log` | Zero unhandled exceptions or fatal errors recorded | [ ] |

