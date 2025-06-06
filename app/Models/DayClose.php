<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayClose extends Model
{
    use HasFactory;

    protected $table = 'day_close';

    protected $fillable = [
        'store_id',
        'working_date',
        'next_working_date',
        'total_sales',
        'total_orders',
        'settled_orders',
        'settled_amount',
        'voided_orders',
        'completed_orders',
        'total_expenses',
        'total_purchases',
        'remaining_amount',
        'is_locked',
        'closed_at',
    ];

    protected $casts = [
        'working_date' => 'date',
        'next_working_date' => 'date',
        'total_sales' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'is_locked' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /**
     * Get the store that owns the day close record.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}