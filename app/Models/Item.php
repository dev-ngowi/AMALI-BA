<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'item_type_id',
        'item_group_id',
        'expire_date',
        'status',
        'version',
        'last_modified',
        'is_synced',
        'operation'
    ];

    protected $casts = [
        'expire_date' => 'date',
        'is_synced' => 'boolean',
        'last_modified' => 'datetime'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function itemType()
    {
        return $this->belongsTo(ItemType::class);
    }

    public function itemGroup()
    {
        return $this->belongsTo(ItemGroup::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function itemStocks()
    {
        return $this->hasMany(ItemStock::class);
    }

    public function itemUnits()
    {
        return $this->hasOne(ItemUnit::class);
    }

    public function itemBarcodes()
    {
        return $this->hasMany(ItemBarcode::class);
    }

    public function itemImages()
    {
        return $this->hasMany(ItemImage::class);
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

    public function brand()
    {
        return $this->hasOne(BrandApplicableItem::class);
    }
}