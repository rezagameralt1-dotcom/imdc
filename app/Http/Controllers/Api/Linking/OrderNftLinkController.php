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

        // Check permission
        if (!$user->hasPermission('linking.admin')) {
            if (!$user->hasPermission('linking.create')) {
                return $this->errorResponse('Unauthorized: linking.create permission required', 403);
            }

            // Ownership checks: both Order and NFT must belong to user
            $order = Order::find($data['order_id']);
            if (!$order) {
                return $this->errorResponse('Order not found', 404);
            }

            // Order ownership check (conservative: require admin if can't verify)
            if (isset($order->shop_customer_id)) {
                // Try to verify ownership via shop_customer_id -> user mapping
                // For now, if we can't verify, require admin
                if (!$user->hasPermission('linking.admin')) {
                    return $this->errorResponse('Unauthorized: Cannot verify order ownership without linking.admin permission', 403);
                }
            } else {
                // No shop_customer_id, require admin
                if (!$user->hasPermission('linking.admin')) {
                    return $this->errorResponse('Unauthorized: Cannot verify order ownership without linking.admin permission', 403);
                }
            }

            $nft = NftToken::find($data['nft_id']);
            if (!$nft) {
                return $this->errorResponse('NFT not found', 404);
            }

            if ($nft->owner_user_id != $user->id) {
                return $this->errorResponse('Unauthorized: NFT does not belong to user', 403);
            }
        }

        try {
            $link = $this->linkingService->createOrderNftLink(
                $data['order_id'],
                $data['nft_id'],
                $data['purpose'] ?? 'fulfillment',
                $user->id,
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
