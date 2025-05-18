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
        'remarks'
    ];

    public function items()
    {
        return $this->hasMany(GoodReceiveNoteItem::class, 'grn_id');
    }
}