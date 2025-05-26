<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemStock extends Model
{
    protected $fillable = [
        'item_id',
        'stock_id',
        'stock_quantity',
        'version',
        'last_modified',
        'is_synced',
        'operation'
    ];

    protected $casts = [
        'stock_quantity' => 'float',
        'is_synced' => 'boolean',
        'last_modified' => 'datetime'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}