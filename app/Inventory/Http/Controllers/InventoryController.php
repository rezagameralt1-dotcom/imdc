<?php

namespace App\Inventory\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Inventory\Http\Requests\AdjustInventoryRequest;
use App\Inventory\Http\Requests\ReserveInventoryRequest;
use App\Inventory\Models\InventoryItem;
use App\Inventory\Services\InventoryService;
use App\Orders\Models\Order;

class InventoryController extends ApiController
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function show(string $productId)
    {
        $item = InventoryItem::query()->where('product_id', $productId)->first();

        if (!$item) {
            $item = new InventoryItem([
                'product_id' => $productId,
                'available_quantity' => 0,
                'reserved_quantity' => 0,
            ]);
        }

        $this->authorize('view', $item);

        return $this->successResponse($item);
    }

    public function adjust(string $productId, AdjustInventoryRequest $request)
    {
        $item = InventoryItem::query()->where('product_id', $productId)->first();
        if (!$item) {
            $item = new InventoryItem(['product_id' => $productId]);
        }

        $this->authorize('update', $item);

        $adjusted = $this->service->adjustStock(
            $productId,
            $request->integer('quantity'),
            $request->user(),
            $request->attributes->get('trace_id')
        );

        return $this->successResponse($adjusted);
    }

    public function reserve(ReserveInventoryRequest $request)
    {
        $order = Order::with('items')->findOrFail($request->string('order_id'));

        $this->authorize('view', $order);

        $success = $this->service->reserveForOrder($order, $request->user());

        return $success
            ? $this->successResponse(['status' => 'reserved'])
            : $this->errorResponse('Reservation failed', 409);
    }
}

