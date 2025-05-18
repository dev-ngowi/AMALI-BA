<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'unit_id',
        'quantity',
        'discount',
        'unit_price',
        'selling_price',
        'selling_unit_id',
        'tax_id',
        'total_price'
    ];
}