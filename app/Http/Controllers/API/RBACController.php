<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class RBACController extends Controller
{
    public function roles()
    {
        if (! \Schema::hasTable('roles')) {
            return ApiResponse::success(['roles' => []]);
        }
        $rows = DB::table('roles')->select('id', 'name')->orderBy('id')->get();

        return ApiResponse::success(['roles' => $rows]);
    }

    public function attachRole(int $userId, int $roleId)
    {
        if (! \Schema::hasTable('role_user')) {
            return ApiResponse::error('RBAC not provisioned', 400);
        }
        $exists = DB::table('role_user')->where(['user_id' => $userId, 'role_id' => $roleId])->exists();
        if (! $exists) {
            DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId]);
        }

        return ApiResponse::success(['attached' => true]);
    }
}
