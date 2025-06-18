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
        Schema::create('cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->decimal('pos_sales_amount', 10, 2);
            $table->decimal('actual_cash_amount', 10, 2);
            $table->date('sales_date');
            $table->date('reconciliation_date');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
            $table->enum('payment_method', ['CASH', 'CARD', 'MOBILE', 'OTHER'])->default('CASH');
            $table->enum('reconciliation_status', ['PENDING', 'COMPLETED', 'DISCREPANCY'])->default('PENDING');
            $table->decimal('discrepancy_amount', 10, 2)->storedAs('actual_cash_amount - pos_sales_amount');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliations');
    }
};
