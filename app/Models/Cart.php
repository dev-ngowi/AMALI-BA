<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'order_number',
        'customer_type_id',
        'customer_id',
        'total_amount',
        'status',
        'date'
    ];

    protected $casts = [
        'date' => 'datetime'
    ];

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}