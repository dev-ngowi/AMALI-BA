<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city_id',
        'state',
        'postal_code',
        'country_id',
        'contact_person',
        'tin',
        'vrn',
        'status'
    ];
}