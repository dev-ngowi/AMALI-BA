<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'customer_name',
        'customer_type_id',
        'city_id',
        'phone',
        'email',
        'address',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}