<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'item_id',
        'store_id',
        'min_quantity',
        'max_quantity'
    ];

    protected $casts = [
        'min_quantity' => 'float',
        'max_quantity' => 'float'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function itemStocks()
    {
        return $this->hasMany(ItemStock::class);
    }
}