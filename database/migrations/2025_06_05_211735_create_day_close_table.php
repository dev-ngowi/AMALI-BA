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
        Schema::create('day_close', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->date('working_date');
            $table->date('next_working_date');
            $table->decimal('total_sales', 10, 2)->default(0.00);
            $table->integer('total_orders')->default(0);
            $table->integer('settled_orders')->default(0);
            $table->decimal('settled_amount', 10, 2)->default(0.00);
            $table->integer('voided_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->decimal('total_expenses', 10, 2)->default(0.00);
            $table->decimal('total_purchases', 10, 2)->default(0.00);
            $table->decimal('remaining_amount', 10, 2)->default(0.00);
            $table->boolean('is_locked')->default(true);
            $table->dateTime('closed_at');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');

            // Unique constraint
            $table->unique(['store_id', 'working_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_close');
    }
};