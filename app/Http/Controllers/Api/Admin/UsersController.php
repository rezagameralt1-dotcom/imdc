<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Services\Admin\AdminUsersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

class UsersController extends ApiController
{
    public function __construct(
        private readonly AdminUsersService $adminUsersService,
    ) {
    }

    /**
     * Get users list with pagination, filtering, and sorting
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'email' => $request->input('email'),
                'name' => $request->input('name'),
                'role' => $request->input('role'),
            ];
            
            // Remove null filters
            $filters = array_filter($filters, fn($value) => $value !== null);
            
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');
            $limit = (int) $request->input('limit', 50);
            $offset = (int) $request->input('offset', 0);
            
            $data = $this->adminUsersService->getUsers($filters, $sortBy, $sortOrder, $limit, $offset);
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user by ID
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->adminUsersService->getUserById($id);
            
            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }
            
            return $this->successResponse($user);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign roles to user (idempotent)
     */
    public function assignRoles(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'roles' => 'required|array',
                'roles.*' => 'required|string',
            ]);
            
            $roleNames = $request->input('roles');
            $user = $this->adminUsersService->assignRoles($id, $roleNames);
            
            return $this->successResponse($user);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to assign roles: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign permissions to user (idempotent)
     */
    public function assignPermissions(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'required|string',
            ]);
            
            $permissionNames = $request->input('permissions');
            $user = $this->adminUsersService->assignPermissions($id, $permissionNames);
            
            return $this->successResponse($user);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to assign permissions: ' . $e->getMessage(), 500);
        }
    }
}
