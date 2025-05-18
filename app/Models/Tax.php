<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = [
        'name',
        'tax_type',
        'tax_mode',
        'tax_percentage',
        'tax_amount'
    ];

    protected $casts = [
        'tax_type' => 'string',
        'tax_mode' => 'string',
        'tax_percentage' => 'float',
        'tax_amount' => 'float'
    ];
}