<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran - {{ $transaction->invoice_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace, sans-serif;
            font-size: 12px;
            line-height: 1.3;
            color: #000;
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        .receipt-container {
            background-color: #fff;
            width: 100%;
            max-width: 80mm;
            padding: 12px 10px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .double-divider {
            border-top: 2px dashed #000;
            margin: 8px 0;
        }

        .store-header {
            margin-bottom: 8px;
        }

        .store-name {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .meta-table, .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }

        .meta-table td {
            padding: 1px 0;
            font-size: 11px;
        }

        .items-table th {
            font-size: 11px;
            border-bottom: 1px dashed #000;
            padding-bottom: 4px;
        }

        .items-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .item-name {
            font-weight: bold;
        }

        .total-section {
            margin-top: 6px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: bold;
            padding: 2px 0;
        }

        .footer {
            margin-top: 12px;
            font-size: 11px;
            color: #333;
        }

        .actions-bar {
            margin-bottom: 16px;
            display: flex;
            gap: 10px;
        }

        .btn {
            background-color: #f59e0b;
            color: #000;
            font-weight: bold;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
        }

        /* Thermal Printer Specific CSS (58mm / 80mm) */
        @media print {
            @page {
                size: auto;
                margin: 0;
            }

            body {
                background-color: #fff;
                padding: 0;
                margin: 0;
            }

            .receipt-container {
                width: 100%;
                max-width: 100%;
                box-shadow: none;
                padding: 4mm;
            }

            .actions-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- On-screen Action Controls (Hidden on print) -->
    <div class="actions-bar">
        <button onclick="window.print()" class="btn">🖨️ Cetak Struk (Print)</button>
        <button onclick="window.close()" class="btn btn-secondary">Tutup</button>
    </div>

    <div class="receipt-container">
        <!-- Store Header -->
        <div class="store-header text-center">
            <div class="store-name">{{ config('app.name', 'POS & INVENTORY') }}</div>
            <div style="font-size: 10px; color: #555;">Struk Resmi Transaksi Penjualan</div>
        </div>

        <div class="divider"></div>

        <!-- Transaction Meta -->
        <table class="meta-table">
            <tr>
                <td style="width: 35%;">No. Invoice</td>
                <td>: <span class="font-bold">{{ $transaction->invoice_number }}</span></td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>: {{ $transaction->created_at->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td>Kasir</td>
                <td>: {{ $transaction->creator?->name ?? 'Kasir Utama' }}</td>
            </tr>
            <tr>
                <td>Channel</td>
                <td>: {{ strtoupper($transaction->channel->value) }}</td>
            </tr>
            <tr>
                <td>Pembayaran</td>
                <td>: {{ strtoupper($transaction->payment_method->value) }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- Line Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-left">Item / SKU</th>
                    <th class="text-center" style="width: 15%;">Qty</th>
                    <th class="text-right" style="width: 25%;">Harga</th>
                    <th class="text-right" style="width: 30%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaction->items as $item)
                    <tr>
                        <td colspan="4" class="item-name" style="padding-top: 4px;">
                            {{ $item->product->name }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 10px; color: #555;">{{ $item->product->sku }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format((float)$item->price, 0, ',', '.') }}</td>
                        <td class="text-right font-bold">{{ number_format((float)bcmul((string)$item->price, (string)$item->quantity, 2), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="double-divider"></div>

        <!-- Grand Total Summary -->
        <div class="total-section">
            <div class="total-row">
                <span>TOTAL BELANJA</span>
                <span>Rp {{ number_format((float)$transaction->total_amount, 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Footer -->
        <div class="footer text-center">
            <p>Terima kasih atas kunjungan Anda!</p>
            <p style="font-size: 9px; margin-top: 4px; color: #777;">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
        </div>
    </div>

</body>
</html>

