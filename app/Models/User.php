<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    // چون در جداول roles/permissions ستون guard_name را "web" گذاشتی:
    protected $guard_name = 'web';

    protected $fillable = ['name','email','password'];
    protected $hidden   = ['password','remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // سازگاری با کد قدیمی (اگه میدلور از hasPermission استفاده می‌کنه)
    public function hasPermission(string $perm): bool
    {
        return $this->hasPermissionTo($perm);
    }
}
