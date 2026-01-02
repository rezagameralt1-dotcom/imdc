<?php

namespace App\Services\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class AdminUsersService
{
    /**
     * Get users list with pagination, filtering, and sorting
     *
     * @param array $filters
     * @param string|null $sortBy
     * @param string $sortOrder
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getUsers(
        array $filters = [],
        ?string $sortBy = 'id',
        string $sortOrder = 'asc',
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = User::on('core');
        
        // Apply filters
        if (isset($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }
        
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
        
        if (isset($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }
        
        // Get total count before pagination
        $total = $query->count();
        
        // Apply sorting
        $allowedSortFields = ['id', 'name', 'email', 'created_at', 'updated_at'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';
        
        $query->orderBy($sortBy, $sortOrder);
        
        // Apply pagination
        $users = $query->limit($limit)->offset($offset)->with('roles')->get();
        
        return [
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ];
            })->toArray(),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Get user by ID with roles
     *
     * @param int $userId
     * @return array|null
     */
    public function getUserById(int $userId): ?array
    {
        $user = User::on('core')->with('roles')->find($userId);
        
        if (!$user) {
            return null;
        }
        
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Assign roles to user (idempotent)
     *
     * @param int $userId
     * @param array $roleNames
     * @return array
     */
    public function assignRoles(int $userId, array $roleNames): array
    {
        return DB::connection('core')->transaction(function () use ($userId, $roleNames) {
            $user = User::on('core')->findOrFail($userId);
            
            // Validate role names exist
            $validRoles = Role::on('core')->whereIn('name', $roleNames)->pluck('name')->toArray();
            $invalidRoles = array_diff($roleNames, $validRoles);
            
            if (!empty($invalidRoles)) {
                throw new \DomainException('Invalid role names: ' . implode(', ', $invalidRoles));
            }
            
            // Sync roles (idempotent: same roles = no change)
            // Spatie Permission's syncRoles accepts role names (strings) or role models
            $user->syncRoles($validRoles);
            
            // Refresh to get updated roles
            $user->refresh();
            $user->load('roles');
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Assign permissions to user (idempotent)
     *
     * @param int $userId
     * @param array $permissionNames
     * @return array
     */
    public function assignPermissions(int $userId, array $permissionNames): array
    {
        return DB::connection('core')->transaction(function () use ($userId, $permissionNames) {
            $user = User::on('core')->findOrFail($userId);
            
            // Validate permission names exist
            $validPermissions = Permission::on('core')->whereIn('name', $permissionNames)->pluck('name')->toArray();
            $invalidPermissions = array_diff($permissionNames, $validPermissions);
            
            if (!empty($invalidPermissions)) {
                throw new \DomainException('Invalid permission names: ' . implode(', ', $invalidPermissions));
            }
            
            // Sync permissions (idempotent: same permissions = no change)
            // Spatie Permission's syncPermissions accepts permission names (strings) or permission models
            $user->syncPermissions($validPermissions);
            
            // Refresh to get updated permissions
            $user->refresh();
            $user->load('permissions');
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => $user->permissions->pluck('name')->toArray(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Get all roles
     *
     * @return array
     */
    public function getRoles(): array
    {
        $roles = Role::on('core')->orderBy('name')->get();
        
        return [
            'roles' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name ?? 'api',
                    'created_at' => $role->created_at?->toIso8601String(),
                    'updated_at' => $role->updated_at?->toIso8601String(),
                ];
            })->toArray(),
        ];
    }

    /**
     * Get all permissions
     *
     * @return array
     */
    public function getPermissions(): array
    {
        $permissions = Permission::on('core')->orderBy('name')->get();
        
        return [
            'permissions' => $permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name ?? 'api',
                    'created_at' => $permission->created_at?->toIso8601String(),
                    'updated_at' => $permission->updated_at?->toIso8601String(),
                ];
            })->toArray(),
        ];
    }

    /**
     * Assign permissions to role (idempotent)
     *
     * @param string $roleName
     * @param array $permissionNames
     * @return array
     */
    public function assignPermissionsToRole(string $roleName, array $permissionNames): array
    {
        return DB::connection('core')->transaction(function () use ($roleName, $permissionNames) {
            // Use Spatie Role model for permission assignment
            SpatieRole::setConnection('core');
            $role = SpatieRole::where('name', $roleName)->first();
            
            if (!$role) {
                throw new \DomainException("Role not found: {$roleName}");
            }
            
            // Validate permission names exist
            $validPermissions = Permission::on('core')
                ->whereIn('name', $permissionNames)
                ->pluck('name')
                ->toArray();
            $invalidPermissions = array_diff($permissionNames, $validPermissions);
            
            if (!empty($invalidPermissions)) {
                throw new \DomainException('Invalid permission names: ' . implode(', ', $invalidPermissions));
            }
            
            // Use Spatie's givePermissionTo for idempotent assignment (no duplicates)
            foreach ($validPermissions as $permissionName) {
                $role->givePermissionTo($permissionName);
            }
            
            // Refresh to get updated permissions
            $role->refresh();
            $role->load('permissions');
            
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name ?? 'api',
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'updated_at' => $role->updated_at?->toIso8601String(),
            ];
        });
    }
}
