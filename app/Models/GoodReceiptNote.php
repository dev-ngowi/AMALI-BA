<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodReceiptNote extends Model
{
    protected $fillable = [
        'grn_number',
        'purchase_order_id',
        'supplier_id',
        'store_id',
        'received_by',
        'received_date',
        'delivery_note_number',
        'status',
        'remarks',
    ];

    protected $casts = [
        'received_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Vendor::class, 'supplier_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(GoodReceiveNoteItem::class, 'grn_id');
    }
}