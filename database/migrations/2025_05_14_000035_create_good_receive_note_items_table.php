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
        Schema::create('good_receive_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('good_receipt_notes')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->float('ordered_quantity');
            $table->float('received_quantity');
            $table->float('accepted_quantity');
            $table->float('rejected_quantity')->default(0.00);
            $table->float('unit_price');
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->float('selling_price');
            $table->string('received_condition')->default('Good');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('grn_id');
            $table->index('purchase_order_item_id');
            $table->index('item_id');
            $table->index('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('good_receive_note_items');
    }
};
