<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'name',
        'location',
        'manager_id'
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function itemStores()
    {
        return $this->hasMany(ItemStore::class);
    }

    public function itemCosts()
    {
        return $this->hasMany(ItemCost::class);
    }

    public function itemPrices()
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function itemTaxes()
    {
        return $this->hasMany(ItemTax::class);
    }
}