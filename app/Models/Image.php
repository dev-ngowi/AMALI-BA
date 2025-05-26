<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
        'file_path'
    ];

    public function itemImages()
    {
        return $this->hasMany(ItemImage::class);
    }
}