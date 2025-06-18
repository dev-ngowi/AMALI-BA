<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'store_id',
        'shift_start',
        'shift_end',
        'shift_status',
        'total_cash_handled',
        'notes',
    ];

    protected $dates = ['shift_start', 'shift_end', 'created_at', 'updated_at', 'deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}