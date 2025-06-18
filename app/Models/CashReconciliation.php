<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashReconciliation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'pos_sales_amount',
        'actual_cash_amount',
        'sales_date',
        'reconciliation_date',
        'user_id',
        'shift_id',
        'store_id',
        'payment_method',
        'reconciliation_status',
        'notes',
    ];

    protected $dates = ['sales_date', 'reconciliation_date', 'created_at', 'updated_at', 'deleted_at'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
