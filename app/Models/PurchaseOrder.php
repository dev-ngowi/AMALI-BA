<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'order_number',
        'supplier_id',
        'store_id',
        'order_date',
        'expected_delivery_date',
        'status',
        'total_amount',
        'currency',
        'notes'
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}