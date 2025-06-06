<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayOpen extends Model
{
    use HasFactory;

    protected $table = 'day_open';

    protected $fillable = [
        'store_id',
        'working_date',
        'opening_balance',
        'opened_at',
        'is_open',
    ];

    protected $casts = [
        'working_date' => 'date',
        'opened_at' => 'datetime',
        'opening_balance' => 'decimal:2',
        'is_open' => 'boolean',
    ];

    /**
     * Get the store that owns the day open record.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}