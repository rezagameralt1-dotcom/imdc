<?php

namespace App\Models\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function hasPermission(string|array $permissions): bool
    {
        $permissions = (array) $permissions;

        if ($this->permissions()->whereIn('name', $permissions)->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->whereIn('name', $permissions))
            ->exists();
    }

    public function assignRole(Role|string $role): void
    {
        $roleModel = $role instanceof Role ? $role : Role::where('name', $role)->first();

        if ($roleModel) {
            $this->roles()->syncWithoutDetaching([$roleModel->id]);
        }
    }
}



