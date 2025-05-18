<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'short_code',
        'payment_method',
        'payment_type_id'
    ];

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }
}