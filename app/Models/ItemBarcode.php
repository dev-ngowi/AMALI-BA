<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBarcode extends Model
{
    protected $fillable = [
        'item_id',
        'barcode_id'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function barcode()
    {
        return $this->belongsTo(Barcode::class);
    }
}