<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DamageStock extends Model
{
    use SoftDeletes;

    protected $fillable = ['item_id', 'quantity', 'damage_date', 'reason', 'status', 'notes'];

    protected $dates = ['damage_date', 'created_at', 'updated_at', 'deleted_at'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
