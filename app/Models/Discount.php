<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discount extends Model
{
    protected $table = 'discounts';

    protected $fillable = [
        'item_id',
        'store_id',
        'type',
        'discount_min',
        'discount_max',
        'discount_start_date',
        'discount_end_date',
    ];

    protected $casts = [
        'discount_start_date' => 'datetime',
        'discount_end_date' => 'datetime',
    ];

    /**
     * Get the item that the discount applies to.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the store that the discount applies to.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}