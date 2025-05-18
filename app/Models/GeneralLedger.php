<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralLedger extends Model
{
    protected $fillable = [
        'transaction_date',
        'account_id',
        'description',
        'debit_amount',
        'credit_amount',
        'reference_type',
        'reference_id',
        'store_id'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit_amount' => 'float',
        'credit_amount' => 'float'
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}