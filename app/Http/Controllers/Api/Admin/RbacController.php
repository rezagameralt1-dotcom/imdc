<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Services\Admin\AdminUsersService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DomainException;

class RbacController extends ApiController
{
    public function __construct(
        private readonly AdminUsersService $adminUsersService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Get all roles
     */
    public function roles(): JsonResponse
    {
        try {
            $data = $this->adminUsersService->getRoles();
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve roles: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all permissions
     */
    public function permissions(): JsonResponse
    {
        try {
            $data = $this->adminUsersService->getPermissions();
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve permissions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign permissions to role (idempotent)
     */
    public function assignPermissionsToRole(Request $request, string $role): JsonResponse
    {
        try {
            $request->validate([
                'permissions' => 'required|array|min:1',
                'permissions.*' => 'required|string',
            ]);
            
            $permissionNames = $request->input('permissions');
            $roleData = $this->adminUsersService->assignPermissionsToRole($role, $permissionNames);
            
            // Audit log
            $user = auth()->user();
            $traceId = (string) Str::uuid();
            $this->auditLogger->log(
                'admin.rbac.role.permissions.assign',
                $role,
                $user,
                [
                    'role_id' => $roleData['id'],
                    'role_name' => $roleData['name'],
                    'permissions' => $permissionNames,
                ],
                $traceId
            );
            
            return $this->successResponse($roleData);
        } catch (DomainException $e) {
            $errorMessage = $e->getMessage();
            $details = null;
            
            // Include details if available (from service exception)
            if (isset($e->guard) && isset($e->missing)) {
                $details = [
                    'guard' => $e->guard,
                    'missing' => $e->missing,
                    'requested' => $e->requested ?? [],
                ];
            }
            
            return $this->errorResponse($errorMessage, 422, $details);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to assign permissions: ' . $e->getMessage(), 500);
        }
    }
}
