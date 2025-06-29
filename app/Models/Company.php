<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'country_id',
        'state',
        'email',
        'website',
        'phone',
        'post_code',
        'tin_no',
        'vrn_no',
        'user_id',
        'company_logo',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}