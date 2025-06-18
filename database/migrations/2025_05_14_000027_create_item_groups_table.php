<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
       public function up(): void
       {
           Schema::create('item_groups', function (Blueprint $table) {
               $table->id();
               $table->string('name');
               $table->unsignedBigInteger('store_id')->nullable();
               $table->timestamps();

               // Only add foreign key if store_id is intended to reference stores table
               $table->foreign('store_id', 'item_groups_store_id_foreign')
                     ->references('id')
                     ->on('stores')
                     ->onDelete('set null');
           });
       }

       public function down(): void
       {
           Schema::table('item_groups', function (Blueprint $table) {
               // Check if foreign key exists before dropping
               if (Schema::hasIndex('item_groups', 'item_groups_store_id_foreign')) {
                   $table->dropForeign(['store_id']);
               }
               $table->dropIfExists('item_groups');
           });
       }
   };