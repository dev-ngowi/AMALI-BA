<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'receipt_number',
        'date',
        'customer_type_id',
        'store_id',
        'total_amount',
        'tip',
        'discount',
        'ground_total',
        'is_active',
        'status',
        'version',
        'last_modified',
        'is_synced',
        'operation'
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'float',
        'tip' => 'float',
        'discount' => 'float',
        'ground_total' => 'float',
        'is_active' => 'boolean',
        'is_synced' => 'boolean',
        'last_modified' => 'datetime'
    ];

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderPayments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function customerOrders()
    {
        return $this->hasMany(CustomerOrder::class);
    }
}