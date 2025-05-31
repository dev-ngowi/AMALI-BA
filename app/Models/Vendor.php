<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'id', 'name', 'email', 'phone', 'address', 'city_id', 'state',
        'postal_code', 'country_id', 'contact_person', 'tin', 'vrn', 'status'
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}