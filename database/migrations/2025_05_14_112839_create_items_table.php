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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_group_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expire_date')->nullable();
            $table->string('status')->default('active');
            $table->integer('version')->default(1);
            $table->timestamp('last_modified')->nullable();
            $table->boolean('is_synced')->default(false);
            $table->string('operation')->nullable()->default('create');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
            $table->index('category_id');
            $table->index('item_type_id');
            $table->index('item_group_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
