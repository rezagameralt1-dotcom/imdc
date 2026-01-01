<?php

namespace App\Http\Controllers\Api\Linking;

use App\Http\Controllers\ApiController;
use App\Services\Linking\LinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkingQueryController extends ApiController
{
    public function __construct(
        private readonly LinkingService $linkingService,
    ) {
    }

    public function getByDid(Request $request, string $didId): JsonResponse
    {
        $user = $request->user();

        // Check permission
        if (!$user->hasPermission('linking.admin') && !$user->hasPermission('linking.read')) {
            return $this->errorResponse('Unauthorized: linking.read permission required', 403);
        }

        try {
            $links = $this->linkingService->getLinksForDid($didId);
            return $this->successResponse($links);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve links: ' . $e->getMessage(), 500);
        }
    }

    public function getByOrder(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();

        // Check permission
        if (!$user->hasPermission('linking.admin') && !$user->hasPermission('linking.read')) {
            return $this->errorResponse('Unauthorized: linking.read permission required', 403);
        }

        try {
            $links = $this->linkingService->getLinksForOrder($orderId);
            return $this->successResponse($links);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve links: ' . $e->getMessage(), 500);
        }
    }

    public function getByNft(Request $request, string $nftId): JsonResponse
    {
        $user = $request->user();

        // Check permission
        if (!$user->hasPermission('linking.admin') && !$user->hasPermission('linking.read')) {
            return $this->errorResponse('Unauthorized: linking.read permission required', 403);
        }

        try {
            $links = $this->linkingService->getLinksForNft($nftId);
            return $this->successResponse($links);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve links: ' . $e->getMessage(), 500);
        }
    }
}
