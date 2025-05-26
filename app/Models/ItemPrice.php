<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPrice extends Model
{
    protected $fillable = [
        'item_id',
        'store_id',
        'unit_id',
        'amount'
    ];

    protected $casts = [
        'amount' => 'float'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}