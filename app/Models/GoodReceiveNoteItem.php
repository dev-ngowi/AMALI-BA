<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodReceiveNoteItem extends Model
{
    protected $fillable = [
        'grn_id',
        'purchase_order_item_id',
        'item_id',
        'ordered_quantity',
        'received_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'unit_price',
        'unit_id',
        'selling_price',
        'received_condition',
    ];

    protected $casts = [
        'ordered_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'accepted_quantity' => 'decimal:2',
        'rejected_quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function goodReceiptNote()
    {
        return $this->belongsTo(GoodReceiptNote::class, 'grn_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}