<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemStore extends Model
{
    protected $fillable = [
        'item_id',
        'store_id'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}