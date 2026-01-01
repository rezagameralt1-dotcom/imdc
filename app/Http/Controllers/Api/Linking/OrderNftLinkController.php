<?php

namespace App\Http\Controllers\Api\Linking;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Linking\CreateOrderNftLinkRequest;
use App\Services\Linking\LinkingService;
use App\Orders\Models\Order;
use App\Nfts\Models\NftToken;
use Illuminate\Http\JsonResponse;

class OrderNftLinkController extends ApiController
{
    public function __construct(
        private readonly LinkingService $linkingService,
    ) {
    }

    public function store(CreateOrderNftLinkRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Route middleware ensures Admin role, so ownership checks are bypassed
        // Admin can create any links

        try {
            // created_by is optional; if not provided, set to null (user.id is integer, created_by expects UUID)
            $link = $this->linkingService->createOrderNftLink(
                $data['order_id'],
                $data['nft_id'],
                $data['purpose'] ?? 'fulfillment',
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
