<?php

use App\Enums\SettlementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consignment_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('transaction_item_id')
                ->unique()
                ->constrained('transaction_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2)->unsigned();
            $table->string('status')->default(SettlementStatus::Unpaid->value);
            $table->string('payout_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consignment_settlements');
    }
};
