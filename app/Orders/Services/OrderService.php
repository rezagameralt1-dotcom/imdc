<?php

namespace App\Orders\Services;

use App\Inventory\Services\InventoryService;
use App\Models\User;
use App\Orders\Jobs\ProcessOrderCreatedJob;
use App\Orders\Models\Order;
use App\Orders\Models\OrderItem;
use App\Products\Models\Product;
use App\Support\AuditLogger;
use App\Support\Events\RedisStreamPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly RedisStreamPublisher $publisher,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function create(array $payload, ?User $user = null, ?string $traceId = null): Order
    {
        $itemsInput = $payload['items'] ?? [];
        if (empty($itemsInput)) {
            throw ValidationException::withMessages(['items' => 'At least one item is required.']);
        }

        $products = Product::whereIn('id', collect($itemsInput)->pluck('product_id'))->get()->keyBy('id');

        $orderTotal = 0;
        $currency = $payload['currency'] ?? 'USD';

        $meta = $payload['meta'] ?? [];

        $order = DB::connection('orders')->transaction(function () use ($user, $itemsInput, $products, &$orderTotal, $currency, $traceId, $meta) {
            /** @var Order $order */
            $order = Order::create([
                'user_id' => $user?->id,
                'status' => Order::STATUS_PENDING,
                'inventory_status' => Order::INVENTORY_PENDING,
                'total_amount' => 0,
                'currency' => $currency,
                'meta' => $meta,
                'trace_id' => $traceId,
            ]);

            foreach ($itemsInput as $item) {
                $product = $products->get($item['product_id']);

                if (!$product) {
                    throw ValidationException::withMessages(['product_id' => 'Product not found: '.$item['product_id']]);
                }

                if ($product->status !== 'active') {
                    throw ValidationException::withMessages(['product_id' => 'Product not available: '.$product->id]);
                }

                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->price;
                $subtotal = $unitPrice * $quantity;
                $orderTotal += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ]);
            }

            $order->total_amount = $orderTotal;
            $order->save();

            return $order;
        });

        $order->load('items');

        $this->publisher->publish('order.created', [
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->toArray(),
            'trace_id' => $traceId,
        ]);

        $this->auditLogger->log('order.created', $order, $user, ['total' => $orderTotal], $traceId);

        // Kick off asynchronous inventory reservation
        dispatch(new ProcessOrderCreatedJob($order->id));

        return $order;
    }

    public function markPaid(Order $order, ?User $user = null, ?string $traceId = null): Order
    {
        if ($order->status === Order::STATUS_PAID) {
            return $order;
        }

        if ($order->inventory_status !== Order::INVENTORY_RESERVED) {
            throw ValidationException::withMessages([
                'order' => 'Inventory not reserved for this order.',
            ]);
        }

        $order->status = Order::STATUS_PAID;
        $order->save();

        $this->publisher->publish('order.paid', [
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'trace_id' => $traceId ?? $order->trace_id,
        ]);

        $this->auditLogger->log('order.paid', $order, $user, [], $traceId ?? $order->trace_id);

        return $order;
    }

    public function cancel(Order $order, ?User $user = null): Order
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            return $order;
        }

        $order->status = Order::STATUS_CANCELLED;
        $order->save();

        $this->inventoryService->releaseForOrder($order, $user);

        $this->publisher->publish('order.cancelled', [
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'trace_id' => $order->trace_id,
        ]);

        $this->auditLogger->log('order.cancelled', $order, $user, [], $order->trace_id);

        return $order;
    }
}

