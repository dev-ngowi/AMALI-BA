<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemUnit extends Model
{
    protected $fillable = [
        'item_id',
        'buying_unit_id',
        'selling_unit_id'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function buyingUnit()
    {
        return $this->belongsTo(Unit::class, 'buying_unit_id');
    }

    public function sellingUnit()
    {
        return $this->belongsTo(Unit::class, 'selling_unit_id');
    }
}