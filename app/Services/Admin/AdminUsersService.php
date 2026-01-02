<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            
            // Validate role names exist and get role IDs (direct DB query, no guard_name)
            $roleIds = DB::connection('core')
                ->table('roles')
                ->whereIn('name', $roleNames)
                ->pluck('id')
                ->toArray();
            
            $validRoleNames = DB::connection('core')
                ->table('roles')
                ->whereIn('name', $roleNames)
                ->pluck('name')
                ->toArray();
            
            $invalidRoles = array_diff($roleNames, $validRoleNames);
            
            if (!empty($invalidRoles)) {
                throw new \DomainException('Invalid role names: ' . implode(', ', $invalidRoles));
            }
            
            // Sync roles via direct pivot table manipulation (idempotent, no guard_name required)
            // Remove all existing roles for this user
            DB::connection('core')
                ->table('model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->where('model_id', $userId)
                ->delete();
            
            // Insert new roles (idempotent: primary key prevents duplicates)
            $modelType = 'App\Models\User';
            $insertData = array_map(function ($roleId) use ($userId, $modelType) {
                return [
                    'role_id' => $roleId,
                    'model_id' => $userId,
                    'model_type' => $modelType,
                ];
            }, $roleIds);
            
            if (!empty($insertData)) {
                DB::connection('core')
                    ->table('model_has_roles')
                    ->insert($insertData);
            }
            
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
}
