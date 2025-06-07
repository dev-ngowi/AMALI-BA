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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('receipt_number')->unique();
            $table->date('date');
            $table->foreignId('customer_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $table->float('total_amount')->nullable();
            $table->float('tip')->nullable();
            $table->float('discount')->nullable();
            $table->float('ground_total')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('completed');
            $table->integer('version')->default(1);
            $table->timestamp('last_modified')->nullable();
            $table->boolean('is_synced')->default(false);
            $table->string('operation')->nullable()->default('create');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('customer_type_id');
            $table->index('store_id');
            $table->index('order_number');
            $table->index('receipt_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
