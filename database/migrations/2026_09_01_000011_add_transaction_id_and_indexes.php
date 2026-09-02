<?php

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
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('transaction_id')
                ->nullable()
                ->after('stock_opname_item_id')
                ->constrained('transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::table('consignment_settlements', function (Blueprint $table): void {
            $table->index(['supplier_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignment_settlements', function (Blueprint $table): void {
            $table->dropIndex(['supplier_id', 'status']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['transaction_id']);
            $table->dropColumn('transaction_id');
        });
    }
};
