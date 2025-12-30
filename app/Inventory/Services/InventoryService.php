<?php

namespace App\Inventory\Services;

use App\Inventory\Models\InventoryItem;
use App\Inventory\Models\InventoryReservation;
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

    public function adjustStock(string $productId, int $delta, ?string $traceId = null): InventoryItem
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
                    'delta' => 'Adjustment would result in negative stock.',
                ]);
            }

            $inventoryItem->available_quantity = $newAvailable;
            $inventoryItem->save();

            return $inventoryItem;
        });

        $this->auditLogger->log('inventory.adjusted', $item, null, [
            'delta' => $delta,
            'available_quantity' => $item->available_quantity,
        ], $traceId);

        DB::connection('inventory')->table('inventory_movements')->insert([
            'product_id' => $productId,
            'delta_available' => $delta,
            'delta_reserved' => 0,
            'reason' => 'adjust',
            'trace_id' => $traceId,
            'created_at' => now(),
        ]);

        return $item;
    }

    /**
     * @param array<int, array{product_id:string,quantity:int}> $items
     */
    public function reserveForOrder(string $orderId, string $shopCustomerId, array $items, ?string $traceId = null): bool
    {
        return DB::connection('inventory')->transaction(function () use ($orderId, $items, $traceId, $shopCustomerId) {
            $failures = [];
            $lockedItems = [];

            foreach ($items as $item) {
                $inv = InventoryItem::query()
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $inv) {
                    $inv = new InventoryItem([
                        'product_id' => $item['product_id'],
                        'available_quantity' => 0,
                        'reserved_quantity' => 0,
                    ]);
                    $inv->save();
                }

                $lockedItems[] = [$inv, $item];

                if ($inv->available_quantity < $item['quantity']) {
                    $failures[] = [
                        'product_id' => $item['product_id'],
                        'reason' => 'Insufficient stock',
                    ];
                }
            }

            if (! empty($failures)) {
                foreach ($failures as $failure) {
                    InventoryReservation::create([
                        'product_id' => $failure['product_id'],
                        'order_id' => $orderId,
                        'quantity' => collect($items)->firstWhere('product_id', $failure['product_id'])['quantity'] ?? 0,
                        'status' => 'failed',
                        'reason' => $failure['reason'],
                    ]);
                }

                $this->publisher->publish('inventory.failed', [
                    'order_id' => $orderId,
                    'failures' => $failures,
                    'trace_id' => $traceId,
                    'shop_customer_id' => $shopCustomerId,
                ]);

                $this->auditLogger->log('inventory.failed', $orderId, null, ['failures' => $failures], $traceId);

                return false;
            }

            foreach ($lockedItems as [$inv, $item]) {
                $inv->available_quantity -= $item['quantity'];
                $inv->reserved_quantity += $item['quantity'];
                $inv->save();

                InventoryReservation::create([
                    'product_id' => $item['product_id'],
                    'order_id' => $orderId,
                    'quantity' => $item['quantity'],
                    'status' => 'reserved',
                ]);

                DB::connection('inventory')->table('inventory_movements')->insert([
                    'product_id' => $item['product_id'],
                    'delta_available' => -$item['quantity'],
                    'delta_reserved' => $item['quantity'],
                    'reason' => 'reserve',
                    'trace_id' => $traceId,
                    'created_at' => now(),
                ]);
            }

            $this->publisher->publish('inventory.reserved', [
                'order_id' => $orderId,
                'items' => $items,
                'trace_id' => $traceId,
                'shop_customer_id' => $shopCustomerId,
            ]);

            $this->auditLogger->log(
                'inventory.reserved',
                $orderId,
                null,
                ['items' => $items, 'shop_customer_id' => $shopCustomerId],
                $traceId
            );

            return true;
        });
    }

    /**
     * @param array<int, array{product_id:string,quantity:int}> $items
     */
    public function finalizeForOrder(string $orderId, array $items, ?string $traceId = null): bool
    {
        return DB::connection('inventory')->transaction(function () use ($orderId, $items, $traceId) {
            foreach ($items as $item) {
                $reservation = InventoryReservation::query()
                    ->where('order_id', $orderId)
                    ->where('product_id', $item['product_id'])
                    ->whereIn('status', ['reserved', 'finalized'])
                    ->lockForUpdate()
                    ->first();

                $inv = InventoryItem::query()
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $inv) {
                    return false;
                }

                $consume = min($item['quantity'], max(0, $inv->reserved_quantity));
                if ($consume > 0) {
                    $inv->reserved_quantity = max(0, $inv->reserved_quantity - $consume);
                    $inv->save();

                    DB::connection('inventory')->table('inventory_movements')->insert([
                        'product_id' => $item['product_id'],
                        'delta_available' => 0,
                        'delta_reserved' => -$consume,
                        'reason' => 'finalize',
                        'trace_id' => $traceId,
                        'created_at' => now(),
                    ]);
                }

                if ($reservation && $reservation->status !== 'finalized') {
                    $reservation->status = 'finalized';
                    $reservation->save();
                }
            }

            $this->auditLogger->log(
                'inventory.finalized',
                $orderId,
                null,
                ['items' => $items],
                $traceId
            );

            return true;
        });
    }

    /**
     * @param array<int, array{product_id:string,quantity:int}> $items
     */
    public function releaseForOrder(string $orderId, array $items, ?string $traceId = null): void
    {
        DB::connection('inventory')->transaction(function () use ($orderId, $items, $traceId) {
            foreach ($items as $item) {
                $inventoryItem = InventoryItem::query()
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                $qty = $item['quantity'];

                $reservation = InventoryReservation::query()
                    ->where('order_id', $orderId)
                    ->where('product_id', $item['product_id'])
                    ->where('status', 'reserved')
                    ->lockForUpdate()
                    ->first();

                if ($reservation) {
                    $reservation->status = 'released';
                    $reservation->save();
                }

                if ($inventoryItem) {
                    $adjust = min($qty, max(0, $inventoryItem->reserved_quantity));
                    if ($adjust <= 0) {
                        continue;
                    }

                    $inventoryItem->reserved_quantity = max(0, $inventoryItem->reserved_quantity - $adjust);
                    $inventoryItem->available_quantity += $adjust;
                    $inventoryItem->save();

                    DB::connection('inventory')->table('inventory_movements')->insert([
                        'product_id' => $item['product_id'],
                        'delta_available' => $adjust,
                        'delta_reserved' => -$adjust,
                        'reason' => 'release',
                        'trace_id' => $traceId,
                        'created_at' => now(),
                    ]);
                }
            }
        });

        $this->auditLogger->log('inventory.released', $orderId, null, ['items' => $items], $traceId);
    }
}


