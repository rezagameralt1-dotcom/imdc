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

        // Check permission
        if (!$user->hasPermission('linking.admin')) {
            if (!$user->hasPermission('linking.create')) {
                return $this->errorResponse('Unauthorized: linking.create permission required', 403);
            }

            // Ownership checks: both DID and Order must belong to user
            $didProfile = DidProfile::where('id', $data['did_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$didProfile) {
                return $this->errorResponse('Unauthorized: DID does not belong to user', 403);
            }

            // Check order ownership (via shop_customer_id or require admin)
            // Since order ownership is ambiguous without schema changes, require admin if can't verify
            $order = Order::find($data['order_id']);
            if (!$order) {
                return $this->errorResponse('Order not found', 404);
            }

            // If order has shop_customer_id and we can map it to user, check ownership
            // Otherwise, require linking.admin permission
            if (isset($order->shop_customer_id)) {
                // Try to verify ownership via shop_customer_id -> user mapping
                // For now, if we can't verify, require admin
                // This is a conservative approach per requirements
                if (!$user->hasPermission('linking.admin')) {
                    return $this->errorResponse('Unauthorized: Cannot verify order ownership without linking.admin permission', 403);
                }
            } else {
                // No shop_customer_id, require admin
                if (!$user->hasPermission('linking.admin')) {
                    return $this->errorResponse('Unauthorized: Cannot verify order ownership without linking.admin permission', 403);
                }
            }
        }

        try {
            $link = $this->linkingService->createDidOrderLink(
                $data['did_id'],
                $data['order_id'],
                $data['scope'] ?? 'ownership',
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
