<?php

namespace App\Orders\Services;

use App\Core\Services\AccountingService;
use App\Inventory\Services\InventoryService;
use App\Orders\Models\Order;
use App\Orders\Models\OrderItem;
use App\Products\Models\Product;
use App\Support\AuditLogger;
use App\Support\Events\RedisStreamPublisher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly RedisStreamPublisher $publisher,
        private readonly AuditLogger $auditLogger,
        private readonly AccountingService $accountingService,
    ) {
    }

    public function create(array $payload, string $shopCustomerId, ?string $traceId = null): array
    {
        $itemsInput = $payload['items'] ?? [];
        if (empty($itemsInput)) {
            throw ValidationException::withMessages(['items' => 'At least one item is required.']);
        }

        // Normalize idempotency_key: trim and limit length (max 128 chars)
        $idempotencyKey = isset($payload['idempotency_key']) && $payload['idempotency_key'] !== null
            ? substr(trim((string) $payload['idempotency_key']), 0, 128)
            : null;

        // STRICT IDEMPOTENCY: Check for existing order BEFORE any side-effects
        // Must filter by both shop_customer_id AND idempotency_key to ensure per-customer idempotency
        if ($idempotencyKey) {
            $existingOrder = Order::where('shop_customer_id', $shopCustomerId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            
            if ($existingOrder) {
                // Return existing order immediately (no side-effects)
                $existingOrder->load('items');
                return ['order' => $existingOrder, 'is_new' => false];
            }
        }

        // No existing order found, proceed with creation
        $products = Product::whereIn('id', collect($itemsInput)->pluck('product_id'))->get()->keyBy('id');

        $orderTotal = 0;
        $currency = $payload['currency'] ?? 'USD';

        $meta = $payload['meta'] ?? [];

        $isNewOrder = true;
        $order = DB::connection('orders')->transaction(function () use ($itemsInput, $products, &$orderTotal, $currency, $traceId, $meta, $shopCustomerId, $idempotencyKey, &$isNewOrder) {
            try {
                /** @var Order $order */
                $order = Order::create([
                    'shop_customer_id' => $shopCustomerId,
                    'status' => Order::STATUS_PENDING,
                    'total_amount' => 0,
                    'currency' => $currency,
                    'trace_id' => $traceId,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (QueryException $e) {
                // Handle unique constraint violation (Postgres 23505) - race condition
                // Another request created the order with the same idempotency_key between our check and insert
                if ($e->getCode() === '23505' || str_contains($e->getMessage(), '23505')) {
                    // Fetch the existing order that was just created by another request
                    $existingOrder = Order::where('shop_customer_id', $shopCustomerId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();
                    
                    if ($existingOrder) {
                        // Mark as existing order (idempotent behavior)
                        $isNewOrder = false;
                        $existingOrder->load('items');
                        return $existingOrder;
                    }
                }
                // Re-throw if it's not a unique constraint violation or order not found
                throw $e;
            }

            foreach ($itemsInput as $item) {
                $product = $products->get($item['product_id']);

                if (!$product) {
                    throw ValidationException::withMessages(['items' => ['Product not found: '.$item['product_id']]]);
                }

                if ($product->status !== 'active') {
                    throw ValidationException::withMessages(['items' => ['Product not available: '.$product->id]]);
                }

                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->price;
                $lineTotal = $unitPrice * $quantity;
                $orderTotal += $lineTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ]);
            }

            $order->total_amount = $orderTotal;
            $order->save();

            return $order;
        });

        // If order was returned from race condition (existing order), return immediately
        if (!$isNewOrder) {
            return ['order' => $order, 'is_new' => false];
        }

        $order->load('items');

        // Proceed with inventory reservation and other side-effects for newly created order
        try {
            $reserved = $this->inventoryService->reserveForOrder(
                $order->id,
                $shopCustomerId,
                $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->toArray(),
                $order->trace_id
            );
        } catch (\Throwable $e) {
            $order->delete();
            throw $e;
        }

        if (! $reserved) {
            $order->delete();
            throw ValidationException::withMessages(['items' => ['Inventory reservation failed']]);
        }

        $order->status = Order::STATUS_RESERVED;
        $order->save();

        $this->publisher->publish('order.created', [
            'order_id' => $order->id,
            'shop_customer_id' => $shopCustomerId,
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->toArray(),
            'trace_id' => $traceId,
        ]);

        $this->auditLogger->log('order.created', $order, null, ['total' => $orderTotal, 'shop_customer_id' => $shopCustomerId], $traceId);

        return ['order' => $order, 'is_new' => true];
    }

    public function markPaid(Order $order, ?string $traceId = null): Order
    {
        if ($order->status === Order::STATUS_PAID) {
            // Idempotency + reconciliation:
            // Order may already be PAID (e.g. retried /pay), but inventory might still have a RESERVED reservation.
            $items = $order->items->map(fn ($it) => [
                'product_id' => (string) $it->product_id,
                'quantity' => (int) $it->quantity,
            ])->toArray();

            $this->inventoryService->finalizeForOrder($order->id, $items, $traceId);

            return $order->refresh();
        }

        if (! in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_RESERVED], true)) {
            throw ValidationException::withMessages(['order' => 'Order not payable in current status']);
        }

        $finalized = $this->inventoryService->finalizeForOrder(
            $order->id,
            $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->toArray(),
            $traceId ?? $order->trace_id
        );

        if (! $finalized) {
            throw ValidationException::withMessages(['order' => 'Inventory finalize failed']);
        }

        $order->status = Order::STATUS_PAID;
        $order->save();

        // Create accounting voucher (feature-flagged, non-blocking)
        $this->accountingService->createPaymentVoucher(
            $order->id,
            (float) $order->total_amount,
            $order->currency,
            $traceId ?? $order->trace_id
        );

        $this->publisher->publish('order.paid', [
            'order_id' => $order->id,
            'shop_customer_id' => $order->shop_customer_id,
            'trace_id' => $traceId ?? $order->trace_id,
        ]);

        $this->auditLogger->log('order.paid', $order, null, [], $traceId ?? $order->trace_id);

        return $order;
    }

    public function cancel(Order $order): Order
    {
        if ($order->status === Order::STATUS_CANCELED) {
            return $order;
        }

        if (! in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_RESERVED], true)) {
            throw ValidationException::withMessages(['order' => 'Order not cancelable in current status']);
        }

        DB::connection('orders')->transaction(function () use ($order) {
            $items = $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->toArray();

            $this->inventoryService->releaseForOrder(
                $order->id,
                $items,
                $order->trace_id
            );

            $order->status = Order::STATUS_CANCELED;
            $order->save();
        });

        $this->publisher->publish('order.cancelled', [
            'order_id' => $order->id,
            'shop_customer_id' => $order->shop_customer_id,
            'trace_id' => $order->trace_id,
        ]);

        $this->auditLogger->log('order.cancelled', $order, null, [], $order->trace_id);

        return $order;
    }
}

