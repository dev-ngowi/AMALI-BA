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
        'total_price',
    ];

    protected $casts = [
        'discount' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function sellingUnit()
    {
        return $this->belongsTo(Unit::class, 'selling_unit_id');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    public function goodReceiptNoteItems()
    {
        return $this->hasMany(GoodReceiveNoteItem::class);
    }
}