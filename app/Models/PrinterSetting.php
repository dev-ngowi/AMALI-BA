<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrinterSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'virtual_device_id',
        'printer_name',
        'printer_type',
        'printer_ip',
        'printer_port',
        'paper_size',
        'usb_vendor_id',
        'usb_product_id',
        'associated_printer',
        'drawer_code',
        'character_set',
    ];

    protected $casts = [
        'printer_port' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function virtualDevice()
    {
        return $this->belongsTo(VirtualDevice::class);
    }
}