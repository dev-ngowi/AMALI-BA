<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['item_id', 'order_id', 'movement_type', 'quantity', 'movement_date'];
}
