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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('supplier_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('order_date');
            $table->string('expected_delivery_date')->nullable();
            $table->string('status')->default('draft');
            $table->float('total_amount')->default(0.00);
            $table->string('currency')->default('TZS');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('supplier_id');
            $table->index('store_id');
            $table->index('order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
