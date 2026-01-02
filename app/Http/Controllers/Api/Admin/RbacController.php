<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Services\Admin\AdminUsersService;
use Illuminate\Http\JsonResponse;

class RbacController extends ApiController
{
    public function __construct(
        private readonly AdminUsersService $adminUsersService,
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
}
