<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;

    protected $fillable = ['role_id', 'module_id', 'can_create', 'can_read', 'can_update', 'can_delete'];

    protected $dates = ['created_at', 'deleted_at'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function module()
    {
        return $this->belongsTo(PermissionModule::class, 'module_id');
    }
}