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
        Schema::create('day_open', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->date('working_date');
            $table->decimal('opening_balance', 10, 2)->default(0.00);
            $table->dateTime('opened_at');
            $table->boolean('is_open')->default(true);
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
        Schema::dropIfExists('day_open');
    }
};