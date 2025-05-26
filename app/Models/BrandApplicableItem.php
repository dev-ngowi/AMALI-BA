<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandApplicableItem extends Model
{
    protected $fillable = [
        'item_id',
        'brand_id'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function brand()
    {
        return $this->belongsTo(ItemBrand::class);
    }
}