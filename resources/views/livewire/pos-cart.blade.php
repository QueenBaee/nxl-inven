<div 
    class="space-y-6"
    x-data="{
        playBeep(freq = 800, duration = 0.1, type = 'sine') {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = type;
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + duration);
            } catch (e) {
                // Audio autoplay might be restricted in some browsers
            }
        },
        focusScanner() {
            this.$nextTick(() => {
                const input = document.getElementById('barcode-scanner-input');
                if (input) input.focus();
            });
        }
    }"
    x-init="
        window.addEventListener('play-scan-success', () => playBeep(950, 0.08, 'sine'));
        window.addEventListener('play-scan-error', () => {
            playBeep(250, 0.15, 'sawtooth');
            setTimeout(() => playBeep(200, 0.15, 'sawtooth'), 160);
        });
        window.addEventListener('focus-barcode-input', () => focusScanner());
        focusScanner();
    "
>
    <!-- Dedicated Barcode Scanner Strip -->
    <div class="bg-gray-900 text-white p-4 rounded-2xl shadow-sm border border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="w-10 h-10 rounded-xl bg-amber-500 text-gray-900 flex items-center justify-center font-bold flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-sm text-white">Scanner Barcode Kasir</h3>
                <p class="text-xs text-gray-400">Scan barcode SKU menggunakan scanner USB/Bluetooth</p>
            </div>
        </div>

        <div class="w-full sm:w-80">
            <form wire:submit.prevent="scanBarcode" class="relative">
                <input 
                    id="barcode-scanner-input"
                    type="text" 
                    wire:model="barcode"
                    placeholder="Scan Barcode SKU..." 
                    autocomplete="off"
                    class="block w-full pl-4 pr-10 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-white placeholder-gray-500 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                />
                <button type="submit" class="absolute inset-y-0 right-0 pr-3 flex items-center text-amber-400 hover:text-amber-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>
        </div>
    </div>

    <!-- POS Notifications / Alerts -->
    @if ($errorMessage)
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 flex items-start justify-between shadow-sm transition-all" role="alert">
            <div class="flex items-center space-x-3">
                <svg class="w-6 h-6 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h4 class="font-bold text-red-900">Perhatian / Transaksi Ditolak</h4>
                    <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                </div>
            </div>
            <button wire:click="$set('errorMessage', null)" class="text-red-500 hover:text-red-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    @if ($successMessage)
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm transition-all" role="alert">
            <div class="flex items-center space-x-3">
                <svg class="w-6 h-6 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h4 class="font-bold text-emerald-900">Transaksi Selesai</h4>
                    <p class="text-sm text-emerald-700">{{ $successMessage }}</p>
                </div>
            </div>

            <!-- Post-Checkout Actions: Print Receipt & New Sale -->
            <div class="flex items-center space-x-2">
                @if ($lastTransactionId)
                    <a 
                        href="{{ route('receipt.show', $lastTransactionId) }}" 
                        target="_blank"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Cetak Struk (Receipt)</span>
                    </a>
                @endif
                <button 
                    wire:click="startNewSale" 
                    class="px-4 py-2 bg-white border border-emerald-300 hover:bg-emerald-100 text-emerald-800 font-bold text-xs rounded-lg transition"
                >
                    Transaksi Baru
                </button>
            </div>
        </div>
    @endif

    <!-- Main POS Grid Layout: Catalog (Left) + Cart & Checkout (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT: Product Search & Quick Catalog (7 Columns) -->
        <div class="lg:col-span-7 space-y-4">
            <!-- Search and Filter Bar -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 space-y-3">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="searchQuery" 
                        placeholder="Cari produk manual berdasarkan Nama atau SKU..." 
                        class="block w-full pl-10 pr-10 py-3 border border-gray-200 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-200 text-sm font-medium transition"
                    />
                    @if ($searchQuery)
                        <button wire:click="$set('searchQuery', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>

                <!-- Type Filter Pills -->
                <div class="flex items-center space-x-2 text-xs font-medium">
                    <span class="text-gray-400">Filter:</span>
                    <button wire:click="$set('typeFilter', 'all')" class="px-3 py-1 rounded-full transition {{ $typeFilter === 'all' ? 'bg-amber-500 text-white font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Semua Produk
                    </button>
                    <button wire:click="$set('typeFilter', 'regular')" class="px-3 py-1 rounded-full transition {{ $typeFilter === 'regular' ? 'bg-amber-500 text-white font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Toko / Regular
                    </button>
                    <button wire:click="$set('typeFilter', 'consignment')" class="px-3 py-1 rounded-full transition {{ $typeFilter === 'consignment' ? 'bg-amber-500 text-white font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Konsinyasi
                    </button>
                </div>
            </div>

            <!-- Product Cards Catalog Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @forelse ($this->catalogProducts as $product)
                    @php
                        $isFrozen = $this->isProductFrozen($product->id);
                        $isOutOfStock = $product->stock <= 0;
                        $isDisabled = $isFrozen || $isOutOfStock;
                    @endphp
                    <div 
                        wire:click="{{ ! $isDisabled ? "addItem({$product->id})" : '' }}"
                        class="p-4 rounded-2xl border transition-all relative flex flex-col justify-between select-none
                            {{ $isDisabled ? 'bg-gray-50 border-gray-200 opacity-60 cursor-not-allowed' : 'bg-white border-gray-100 hover:border-amber-400 hover:shadow-md cursor-pointer group active:scale-98' }}"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-1 mb-1">
                                <span class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600 truncate max-w-[120px]">
                                    {{ $product->sku }}
                                </span>
                                @if ($product->type->value === 'consignment')
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200">
                                        Konsinyasi
                                    </span>
                                @endif
                            </div>
                            <h3 class="font-bold text-gray-800 text-sm leading-snug group-hover:text-amber-600 transition line-clamp-2">
                                {{ $product->name }}
                            </h3>
                        </div>

                        <div class="mt-3 pt-2 border-t border-gray-100 flex items-end justify-between">
                            <div>
                                <span class="text-xs text-gray-400 block">Harga Jual</span>
                                <span class="font-extrabold text-sm text-gray-900">
                                    Rp {{ number_format((float)$product->selling_price, 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="text-right">
                                @if ($isFrozen)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                        Terkunci Opname
                                    </span>
                                @elseif ($isOutOfStock)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">
                                        Stok Habis
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $product->stock <= 5 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700' }}">
                                        Stok: {{ $product->stock }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center bg-white rounded-2xl border border-dashed border-gray-200">
                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <p class="mt-2 text-sm font-semibold text-gray-600">Tidak ada produk ditemukan</p>
                        <p class="text-xs text-gray-400">Coba ubah kata kunci pencarian atau ganti filter produk.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- RIGHT: Sticky Shopping Cart & Checkout Summary (5 Columns) -->
        <div class="lg:col-span-5 bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-5 lg:sticky lg:top-4">
            
            <!-- Cart Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-base">Keranjang Penjualan</h2>
                        <p class="text-xs text-gray-400">{{ $this->cartItemCount }} item(s) terpilih</p>
                    </div>
                </div>
                @if (count($items) > 0)
                    <button wire:click="clearCart" class="text-xs text-red-500 hover:text-red-700 font-semibold transition">
                        Kosongkan
                    </button>
                @endif
            </div>

            <!-- Channel & Payment Configuration -->
            <div class="grid grid-cols-2 gap-3 bg-gray-50 p-3 rounded-xl border border-gray-100">
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Sales Channel</label>
                    <select wire:model.live="channel" class="w-full text-xs font-semibold rounded-lg border-gray-200 bg-white shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="offline">Toko / In-Store</option>
                        <option value="shopee">Shopee</option>
                        <option value="tokopedia">Tokopedia</option>
                        <option value="tiktok_shop">TikTok Shop</option>
                        <option value="whatsapp">WhatsApp / Direct</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Metode Bayar</label>
                    <select wire:model.live="paymentMethod" class="w-full text-xs font-semibold rounded-lg border-gray-200 bg-white shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="cash">Tunai (Cash)</option>
                        <option value="qris">QRIS</option>
                        <option value="transfer">Bank Transfer</option>
                    </select>
                </div>
            </div>

            <!-- Cart Items List -->
            <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto pr-1">
                @forelse ($items as $productId => $item)
                    <div class="py-3 flex items-center justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-xs text-gray-900 truncate">{{ $item['name'] }}</h4>
                            <div class="text-[11px] text-gray-500 mt-0.5">
                                Rp {{ number_format((float)$item['price'], 0, ',', '.') }} × {{ $item['quantity'] }}
                            </div>
                        </div>

                        <!-- Quantity Stepper Controls -->
                        <div class="flex items-center space-x-1.5">
                            <button 
                                wire:click="decrementQuantity({{ $productId }})" 
                                class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 flex items-center justify-center font-bold text-sm active:scale-95 transition"
                            >
                                −
                            </button>
                            <span class="w-8 text-center text-xs font-extrabold text-gray-900">
                                {{ $item['quantity'] }}
                            </span>
                            <button 
                                wire:click="incrementQuantity({{ $productId }})" 
                                class="w-7 h-7 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-800 flex items-center justify-center font-bold text-sm active:scale-95 transition"
                            >
                                +
                            </button>
                            <button 
                                wire:click="removeItem({{ $productId }})" 
                                class="w-7 h-7 text-gray-300 hover:text-red-500 flex items-center justify-center ml-1 transition"
                                title="Hapus dari keranjang"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-gray-400">
                        <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <p class="text-xs font-semibold">Keranjang masih kosong</p>
                        <p class="text-[11px] text-gray-400">Scan barcode atau klik produk di samping untuk menambah ke keranjang.</p>
                    </div>
                @endforelse
            </div>

            <!-- Grand Total Calculation -->
            <div class="pt-4 border-t border-gray-100 space-y-2">
                <div class="flex justify-between items-center text-xs text-gray-500">
                    <span>Total Item</span>
                    <span class="font-bold text-gray-700">{{ $this->cartItemCount }} pcs</span>
                </div>
                <div class="flex justify-between items-baseline pt-2 border-t border-dashed border-gray-200">
                    <span class="text-sm font-bold text-gray-900">Total Pembayaran</span>
                    <span class="text-xl font-black text-amber-600">
                        Rp {{ number_format((float)$this->getCartTotal(), 2, ',', '.') }}
                    </span>
                </div>
            </div>

            <!-- Checkout Action Button with Loading State -->
            <button 
                wire:click="checkout"
                wire:loading.attr="disabled"
                @if (empty($items)) disabled @endif
                class="w-full py-3.5 px-4 bg-amber-500 hover:bg-amber-600 active:bg-amber-700 disabled:bg-gray-200 disabled:text-gray-400 text-white font-extrabold text-sm rounded-xl shadow-md transition-all flex items-center justify-center space-x-2"
            >
                <svg wire:loading wire:target="checkout" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="checkout">
                    {{ empty($items) ? 'Pilih Produk untuk Checkout' : 'Proses Transaksi (Bayar)' }}
                </span>
                <span wire:loading wire:target="checkout">
                    Memproses Transaksi...
                </span>
            </button>
        </div>
    </div>
</div>
