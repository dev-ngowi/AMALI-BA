<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemGroup extends Model
{

    protected $fillable = ['name', 'store_id'];

    /**
     * Get the store that owns the item group.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}