<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    protected $fillable = [
        'code'
    ];

    public function itemBarcodes()
    {
        return $this->hasMany(ItemBarcode::class);
    }
}