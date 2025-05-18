<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemType extends Model
{
    protected $fillable = [
        'name'
    ];

    public function items()
    {
        return $this->hasMany(Item::class, 'item_type_id');
    }
}