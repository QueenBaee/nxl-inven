<?php

namespace App\Livewire;

use App\Enums\OpnameStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockOpnameInProgressException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Component;

class PosCart extends Component
{
    /**
     * Scanner input buffer for USB/Bluetooth barcode scanners.
     */
    public string $barcode = '';

    /**
     * Search query for product catalog.
     */
    public string $searchQuery = '';

    /**
     * Filter by product type: 'all', 'regular', 'consignment'.
     */
    public string $typeFilter = 'all';

    /**
     * Cart items keyed by product ID.
     * Shape: [productId => ['product_id' => int, 'name' => string, 'sku' => string, 'price' => string, 'quantity' => int, 'stock' => int, 'is_consignment' => bool]]
     *
     * @var array<int, array{product_id: int, name: string, sku: string, price: string, quantity: int, stock: int, is_consignment: bool}>
     */
    public array $items = [];

    /**
     * Selected payment method.
     */
    public string $paymentMethod = 'cash';

    /**
     * Selected sales channel.
     */
    public string $channel = 'offline';

    /**
     * Feedback messages for UI display.
     */
    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public ?string $lastInvoiceNumber = null;

    public ?int $lastTransactionId = null;

    /**
     * Process a barcode scanned via USB/Bluetooth keyboard-wedge scanner.
     */
    public function scanBarcode(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $code = trim($this->barcode);
        $this->barcode = '';

        if ($code === '') {
            $this->dispatch('focus-barcode-input');

            return;
        }

        // Match product by exact SKU or Name
        /** @var Product|null $product */
        $product = Product::where('sku', $code)
            ->orWhere('name', $code)
            ->first();

        if (! $product) {
            $this->errorMessage = 'Barcode tidak ditemukan.';
            $this->dispatch('play-scan-error');
            $this->dispatch('focus-barcode-input');

            return;
        }

        // Check if product is frozen by active Stock Opname
        if ($this->isProductFrozen($product->id)) {
            $this->errorMessage = 'Produk sedang dikunci karena Stock Opname aktif.';
            $this->dispatch('play-scan-error');
            $this->dispatch('focus-barcode-input');

            return;
        }

        // Check if stock is exhausted
        if ($product->stock <= 0) {
            $this->errorMessage = 'Stok produk habis.';
            $this->dispatch('play-scan-error');
            $this->dispatch('focus-barcode-input');

            return;
        }

        // Check if cart already holds max available stock
        $currentCartQty = $this->items[$product->id]['quantity'] ?? 0;
        if ($currentCartQty >= $product->stock) {
            $this->errorMessage = 'Stok produk tidak mencukupi untuk penambahan.';
            $this->dispatch('play-scan-error');
            $this->dispatch('focus-barcode-input');

            return;
        }

        // Add 1 unit to cart
        $this->addItem($product->id, 1);
        $this->successMessage = "Produk '{$product->name}' berhasil ditambahkan.";
        $this->dispatch('play-scan-success');
        $this->dispatch('focus-barcode-input');
    }

    /**
     * Get search-filtered product catalog for cashiers.
     *
     * @return Collection<int, Product>
     */
    public function getCatalogProductsProperty(): Collection
    {
        return Product::query()
            ->when($this->searchQuery !== '', function ($query) {
                $term = "%{$this->searchQuery}%";
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term);
                });
            })
            ->when($this->typeFilter !== 'all', function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->orderBy('name')
            ->limit(16)
            ->get();
    }

    /**
     * Check if a product is currently frozen by an active Stock Opname.
     */
    public function isProductFrozen(int $productId): bool
    {
        return StockOpname::where('status', OpnameStatus::InProgress)
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }

    /**
     * Add a product to the cart.
     */
    public function addItem(int $productId, int $quantity = 1): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->lastInvoiceNumber = null;

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $product = Product::findOrFail($productId);

        if (isset($this->items[$productId])) {
            $this->items[$productId]['quantity'] += $quantity;
        } else {
            $this->items[$productId] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (string) $product->selling_price,
                'quantity' => $quantity,
                'stock' => (int) $product->stock,
                'is_consignment' => $product->type === ProductType::Consignment,
            ];
        }
    }

    /**
     * Increment quantity by 1.
     */
    public function incrementQuantity(int $productId): void
    {
        if (isset($this->items[$productId])) {
            $this->addItem($productId, 1);
        }
    }

    /**
     * Decrement quantity by 1.
     */
    public function decrementQuantity(int $productId): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (isset($this->items[$productId])) {
            if ($this->items[$productId]['quantity'] > 1) {
                $this->items[$productId]['quantity']--;
            } else {
                $this->removeItem($productId);
            }
        }
    }

    /**
     * Update the quantity of a cart item directly.
     */
    public function updateQuantity(int $productId, int $quantity): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if ($quantity <= 0) {
            $this->removeItem($productId);

            return;
        }

        if (isset($this->items[$productId])) {
            $this->items[$productId]['quantity'] = $quantity;
        }
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(int $productId): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;
        unset($this->items[$productId]);
    }

    /**
     * Clear all items from the cart.
     */
    public function clearCart(): void
    {
        $this->items = [];
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    /**
     * Reset POS ready for new customer sale.
     */
    public function startNewSale(): void
    {
        $this->items = [];
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->lastInvoiceNumber = null;
        $this->lastTransactionId = null;
        $this->dispatch('focus-barcode-input');
    }

    /**
     * Set the payment method.
     */
    public function setPaymentMethod(string|PaymentMethod $paymentMethod): void
    {
        $value = $paymentMethod instanceof PaymentMethod ? $paymentMethod->value : $paymentMethod;

        if (! PaymentMethod::tryFrom($value)) {
            throw new InvalidArgumentException("Invalid payment method: {$value}");
        }

        $this->paymentMethod = $value;
    }

    /**
     * Set the sales channel.
     */
    public function setChannel(string|SalesChannel $channel): void
    {
        $value = $channel instanceof SalesChannel ? $channel->value : $channel;

        if (! SalesChannel::tryFrom($value)) {
            throw new InvalidArgumentException("Invalid sales channel: {$value}");
        }

        $this->channel = $value;
    }

    /**
     * Calculate current cart total using BCMath for zero floating-point drift.
     */
    public function getCartTotal(): float
    {
        $total = array_reduce($this->items, function (string $carry, array $item): string {
            $subtotal = bcmul((string) $item['price'], (string) $item['quantity'], 2);

            return bcadd($carry, $subtotal, 2);
        }, '0.00');

        return (float) $total;
    }

    /**
     * Total item unit count in cart.
     */
    public function getCartItemCountProperty(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }

    /**
     * Execute checkout inside a database transaction with concurrency safeguards.
     *
     * @throws InsufficientStockException|StockOpnameInProgressException|InvalidArgumentException
     */
    public function checkout(): Transaction
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (empty($this->items)) {
            $this->errorMessage = 'Cannot checkout an empty cart.';
            throw new InvalidArgumentException('Cannot checkout an empty cart.');
        }

        $paymentMethodEnum = PaymentMethod::from($this->paymentMethod);
        $channelEnum = SalesChannel::from($this->channel);
        $userId = Auth::id();

        try {
            // Wrap the entire checkout flow in a retryable database transaction
            $transaction = DB::transaction(function () use ($paymentMethodEnum, $channelEnum, $userId): Transaction {
                // Step 1: Generate race-safe daily sequential invoice number with lock
                $invoiceNumber = $this->generateInvoiceNumber();

                $totalAmount = '0.00';
                $preparedItems = [];
                $preparedMovements = [];

                // Step 2: Lock and validate real-time stock for every cart item
                foreach ($this->items as $cartItem) {
                    $productId = (int) $cartItem['product_id'];
                    $requestedQuantity = (int) $cartItem['quantity'];

                    /** @var Product $product */
                    $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

                    if ($product->stock < $requestedQuantity) {
                        throw new InsufficientStockException(
                            productName: $product->name,
                            availableStock: (int) $product->stock,
                            requestedQuantity: $requestedQuantity,
                        );
                    }

                    $sellingPrice = (string) $product->selling_price;
                    $costPrice = (string) $product->cost_price;
                    $isConsignment = $product->type === ProductType::Consignment;

                    $itemSubtotal = bcmul($sellingPrice, (string) $requestedQuantity, 2);
                    $totalAmount = bcadd($totalAmount, $itemSubtotal, 2);

                    $preparedItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $requestedQuantity,
                        'price' => $sellingPrice,
                        'cost_price' => $costPrice,
                        'is_consignment' => $isConsignment,
                    ];

                    $preparedMovements[] = [
                        'product_id' => $product->id,
                        'quantity' => $requestedQuantity,
                    ];
                }

                // Step 3: Create the master Transaction
                $transaction = Transaction::create([
                    'invoice_number' => $invoiceNumber,
                    'total_amount' => $totalAmount,
                    'payment_method' => $paymentMethodEnum,
                    'channel' => $channelEnum,
                    'status' => TransactionStatus::Completed,
                    'created_by' => $userId,
                ]);

                // Step 4: Create historical TransactionItems snapshot
                foreach ($preparedItems as $itemData) {
                    $transaction->items()->create($itemData);
                }

                // Step 5: Create StockMovement entries linked to transaction
                foreach ($preparedMovements as $movementData) {
                    StockMovement::create([
                        'product_id' => $movementData['product_id'],
                        'quantity' => $movementData['quantity'],
                        'type' => StockMovementType::Out,
                        'reference_note' => "Sale {$transaction->invoice_number}",
                        'transaction_id' => $transaction->id,
                        'created_by' => $userId,
                    ]);
                }

                return $transaction;
            }, 5);

            // Reset cart on successful checkout
            $this->items = [];
            $this->lastInvoiceNumber = $transaction->invoice_number;
            $this->lastTransactionId = $transaction->id;
            $this->successMessage = "Transaksi berhasil! Invoice: {$transaction->invoice_number} (Total: Rp ".number_format((float) $transaction->total_amount, 2, ',', '.').')';

            return $transaction;
        } catch (InsufficientStockException $e) {
            $this->errorMessage = "Insufficient stock for product '{$e->productName}'. Available: {$e->availableStock}, requested: {$e->requestedQuantity}.";
            throw $e;
        } catch (StockOpnameInProgressException $e) {
            $this->errorMessage = "Stock mutations are frozen for product ID {$e->productId} due to in-progress stock opname session: {$e->sessionName}";
            throw $e;
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Generate race-safe daily invoice number: INV-{Ymd}-{4-digit sequence}.
     */
    protected function generateInvoiceNumber(): string
    {
        $todayPrefix = 'INV-'.now()->format('Ymd').'-';

        $latestTransaction = Transaction::where('invoice_number', 'like', "{$todayPrefix}%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $nextSequence = 1;

        if ($latestTransaction && preg_match('/-(\d{4})$/', $latestTransaction->invoice_number, $matches)) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return $todayPrefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    public function render(): View
    {
        return view('livewire.pos-cart');
    }
}
