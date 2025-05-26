<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ExpenseItem extends Pivot
{
    protected $table = 'expense_items';
    protected $fillable = ['expense_id', 'item_id'];
}