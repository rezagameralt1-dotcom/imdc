<?php

namespace App\Http\Controllers\Api\Linking;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Linking\CreateDidOrderLinkRequest;
use App\Services\Linking\LinkingService;
use App\Dids\Models\DidProfile;
use App\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DidOrderLinkController extends ApiController
{
    public function __construct(
        private readonly LinkingService $linkingService,
    ) {
    }

    public function store(CreateDidOrderLinkRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Route middleware ensures Admin role, so ownership checks are bypassed
        // Admin can create any links

        try {
            // created_by is optional; if not provided, set to null (user.id is integer, created_by expects UUID)
            $link = $this->linkingService->createDidOrderLink(
                $data['did_id'],
                $data['order_id'],
                $data['scope'] ?? 'ownership',
                null, // created_by: nullable UUID (user.id is integer, cannot map to UUID)
                $this->traceId()
            );

            return $this->successResponse($link, 201);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create link: ' . $e->getMessage(), 500);
        }
    }
}
