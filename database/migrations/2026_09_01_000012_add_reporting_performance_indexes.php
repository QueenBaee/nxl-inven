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
        Schema::table('transactions', function (Blueprint $table): void {
            $table->index(['status', 'created_at']);
        });

        Schema::table('transaction_items', function (Blueprint $table): void {
            $table->index(['created_at', 'product_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['type', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['type', 'stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['type', 'stock']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex(['type', 'created_at']);
            $table->dropIndex(['product_id', 'created_at']);
        });

        Schema::table('transaction_items', function (Blueprint $table): void {
            $table->dropIndex(['created_at', 'product_id']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
