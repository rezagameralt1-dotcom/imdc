<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name','title'];

    public function roles() { return $this->belongsToMany(Role::class, 'permission_role'); }
    public function users() { return $this->belongsToMany(User::class, 'permission_user'); }
}
