<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBrand extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function brandApplicableItems()
    {
        return $this->hasMany(BrandApplicableItem::class, 'brand_id');
    }
}