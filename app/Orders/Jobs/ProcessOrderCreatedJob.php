<?php

namespace App\Orders\Jobs;

use App\Inventory\Services\InventoryService;
use App\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrderCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $orderId)
    {
    }

    public function handle(InventoryService $inventoryService): void
    {
        $order = Order::with('items')->find($this->orderId);

        if (!$order) {
            return;
        }

        $inventoryService->reserveForOrder($order, $order->user ?? null);
    }
}

