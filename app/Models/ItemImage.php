<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemImage extends Model
{
    protected $fillable = [
        'item_id',
        'image_id'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function image()
    {
        return $this->belongsTo(Image::class);
    }
}