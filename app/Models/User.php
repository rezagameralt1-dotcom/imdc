<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name','email','password'];

    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // === RBAC ===
    public function roles() { return $this->belongsToMany(\App\Models\Role::class, 'role_user'); }
    public function permissions() { return $this->belongsToMany(\App\Models\Permission::class, 'permission_user'); }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function hasPermission(string $perm): bool
    {
        if ($this->permissions()->where('name', $perm)->exists()) return true;
        return $this->roles()
            ->whereHas('permissions', function($q) use ($perm){ $q->where('name', $perm); })
            ->exists();
    }
}
