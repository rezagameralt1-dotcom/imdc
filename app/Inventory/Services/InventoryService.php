<?php

namespace App\Inventory\Services;

use App\Inventory\Models\InventoryItem;
use App\Inventory\Models\InventoryReservation;
use App\Models\User;
use App\Orders\Models\Order;
use App\Support\AuditLogger;
use App\Support\Events\RedisStreamPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RedisStreamPublisher $publisher,
    ) {
    }

    public function adjustStock(string $productId, int $delta, ?User $actor = null, ?string $traceId = null): InventoryItem
    {
        /** @var InventoryItem $item */
        $item = DB::connection('inventory')->transaction(function () use ($productId, $delta) {
            $inventoryItem = InventoryItem::query()
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$inventoryItem) {
                $inventoryItem = new InventoryItem([
                    'product_id' => $productId,
                    'available_quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }

            $newAvailable = $inventoryItem->available_quantity + $delta;
            if ($newAvailable < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Adjustment would result in negative stock.',
                ]);
            }

            $inventoryItem->available_quantity = $newAvailable;
            $inventoryItem->save();

            return $inventoryItem;
        });

        $this->auditLogger->log('inventory.adjusted', $item, $actor, [
            'delta' => $delta,
            'available_quantity' => $item->available_quantity,
        ], $traceId);

        return $item;
    }

    public function reserveForOrder(Order $order, ?User $actor = null): bool
    {
        $failures = [];

        foreach ($order->items as $item) {
            DB::connection('inventory')->transaction(function () use ($order, $item, &$failures) {
                $inventoryItem = InventoryItem::query()
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$inventoryItem || $inventoryItem->available_quantity < $item->quantity) {
                    InventoryReservation::create([
                        'product_id' => $item->product_id,
                        'order_id' => $order->id,
                        'quantity' => $item->quantity,
                        'status' => 'failed',
                        'reason' => 'Insufficient stock',
                    ]);

                    $failures[] = [
                        'product_id' => $item->product_id,
                        'reason' => 'Insufficient stock',
                    ];

                    return;
                }

                $inventoryItem->available_quantity -= $item->quantity;
                $inventoryItem->reserved_quantity += $item->quantity;
                $inventoryItem->save();

                InventoryReservation::create([
                    'product_id' => $item->product_id,
                    'order_id' => $order->id,
                    'quantity' => $item->quantity,
                    'status' => 'reserved',
                ]);
            });
        }

        $order->inventory_status = empty($failures) ? Order::INVENTORY_RESERVED : Order::INVENTORY_FAILED;
        $order->save();

        if (empty($failures)) {
            $this->publisher->publish('inventory.reserved', [
                'order_id' => $order->id,
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->toArray(),
                'trace_id' => $order->trace_id,
            ]);
        } else {
            $this->publisher->publish('inventory.failed', [
                'order_id' => $order->id,
                'failures' => $failures,
                'trace_id' => $order->trace_id,
            ]);
        }

        $this->auditLogger->log(
            empty($failures) ? 'inventory.reserved' : 'inventory.failed',
            $order,
            $actor,
            ['failures' => $failures],
            $order->trace_id
        );

        return empty($failures);
    }

    public function releaseForOrder(Order $order, ?User $actor = null): void
    {
        $reservations = InventoryReservation::query()
            ->where('order_id', $order->id)
            ->where('status', 'reserved')
            ->get();

        foreach ($reservations as $reservation) {
            DB::connection('inventory')->transaction(function () use ($reservation) {
                $inventoryItem = InventoryItem::query()
                    ->where('product_id', $reservation->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventoryItem) {
                    $inventoryItem->reserved_quantity = max(
                        0,
                        $inventoryItem->reserved_quantity - $reservation->quantity
                    );
                    $inventoryItem->available_quantity += $reservation->quantity;
                    $inventoryItem->save();
                }

                $reservation->status = 'released';
                $reservation->save();
            });
        }

        $order->inventory_status = Order::INVENTORY_FAILED;
        $order->save();

        $this->auditLogger->log('inventory.released', $order, $actor, [], $order->trace_id);
    }
}

