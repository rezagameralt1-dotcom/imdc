<?php

namespace App\Http\Controllers\Api\Did;

use App\Http\Controllers\ApiController;
use App\Services\Did\DidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DidMeController extends ApiController
{
    public function __construct(
        private readonly DidService $didService,
    ) {
    }

    /**
     * Get current user's DID profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        // Check feature flag
        if (!config('did.enabled', false)) {
            return $this->errorResponse('DID feature is not enabled', 404);
        }

        // Check permission: did.manage.self or Admin
        $user = $request->user();
        if (!$user->hasRole('Admin') && !$user->hasPermission('did.manage.self')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $profile = $this->didService->getByUserId($user->id);

        if (!$profile) {
            return $this->errorResponse('DID profile not found', 404);
        }

        return $this->successResponse($profile);
    }

    /**
     * Create or get current user's DID profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Check feature flag
        if (!config('did.enabled', false)) {
            return $this->errorResponse('DID feature is not enabled', 404);
        }

        // Check permission: did.manage.self or Admin
        $user = $request->user();
        if (!$user->hasRole('Admin') && !$user->hasPermission('did.manage.self')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'wallet_address' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'metadata_json' => 'nullable|array',
        ]);

        $profile = $this->didService->getOrCreate($user->id, $validated);

        return $this->successResponse($profile, 201);
    }

    /**
     * Update current user's DID profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        // Check feature flag
        if (!config('did.enabled', false)) {
            return $this->errorResponse('DID feature is not enabled', 404);
        }

        // Check permission: did.manage.self or Admin
        $user = $request->user();
        if (!$user->hasRole('Admin') && !$user->hasPermission('did.manage.self')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'wallet_address' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'metadata_json' => 'nullable|array',
        ]);

        try {
            $profile = $this->didService->update($user->id, $validated);
            return $this->successResponse($profile);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('DID profile not found', 404);
        } catch (\Exception $e) {
            \Log::error('DID profile update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('DID profile update failed', 500);
        }
    }
}
