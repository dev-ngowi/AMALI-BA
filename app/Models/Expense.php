<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'expense_type',
        'user_id',
        'store_id',
        'expense_date',
        'amount',
        'description',
        'reference_number',
        'receipt_path',
        'linked_shop_item_id'
    ];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'expense_items');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}