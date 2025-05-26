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
        'selling_price',
        'unit_id',
        'received_condition'
    ];
}