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
        Schema::create('printer_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('virtual_device_id');
            $table->string('printer_name');
            $table->string('printer_type'); // e.g., 'network', 'usb', 'cash_drawer'
            $table->string('printer_ip')->nullable(); // For network printers
            $table->integer('printer_port')->default(9100); // For network printers
            $table->string('paper_size')->default('80mm'); // e.g., '80mm', '58mm'
            $table->string('usb_vendor_id')->nullable(); // For USB printers
            $table->string('usb_product_id')->nullable(); // For USB printers
            $table->string('associated_printer')->nullable(); // For cash drawers
            $table->string('drawer_code')->default('\x1b\x70\x19\xfa'); // Escape sequence
            $table->string('character_set')->default('CP437'); // For text encoding
            $table->timestamps();

            $table->foreign('virtual_device_id')->references('id')->on('virtual_devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printer_settings');
    }
};