<?php
namespace App\Observers;

use App\Models\Inventory;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderItemObserver
{
    public function creating(OrderItem $item): void
    {
        DB::transaction(function () use ($item) {
            // Lock inventory row
            $inv = Inventory::where('product_id', $item->product_id)->lockForUpdate()->first();
            if (!$inv) {
                throw new RuntimeException("Inventory for product {$item->product_id} not found.");
            }
            if ($item->qty <= 0) {
                throw new RuntimeException("Order item qty must be > 0.");
            }
            if ($inv->stock < $item->qty) {
                throw new RuntimeException("Insufficient stock for product {$item->product_id}.");
            }
            // reserve
            $inv->stock -= $item->qty;
            $inv->save();

            // ensure price is set (fallback to 0)
            if ($item->price === null) {
                $item->price = 0;
            }
        });
    }

    public function created(OrderItem $item): void
    {
        $this->recalcOrder($item->order_id);
    }

    public function updating(OrderItem $item): void
    {
        DB::transaction(function () use ($item) {
            // Get original qty before update
            $originalQty = $item->getOriginal('qty') ?? 0;
            $delta = ($item->qty ?? 0) - $originalQty;
            if ($delta === 0) return;

            $inv = Inventory::where('product_id', $item->product_id)->lockForUpdate()->first();
            if (!$inv) {
                throw new RuntimeException("Inventory for product {$item->product_id} not found.");
            }

            if ($delta > 0) { // increasing qty -> need more stock
                if ($inv->stock < $delta) {
                    throw new RuntimeException("Insufficient stock to increase qty for product {$item->product_id}.");
                }
                $inv->stock -= $delta;
            } else { // decreasing qty -> release stock
                $inv->stock += abs($delta);
            }
            $inv->save();
        });
    }

    public function updated(OrderItem $item): void
    {
        $this->recalcOrder($item->order_id);
    }

    public function deleted(OrderItem $item): void
    {
        DB::transaction(function () use ($item) {
            $inv = Inventory::where('product_id', $item->product_id)->lockForUpdate()->first();
            if ($inv) {
                $inv->stock += $item->qty;
                $inv->save();
            }
        });
        $this->recalcOrder($item->order_id);
    }

    private function recalcOrder(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $sum = DB::table('order_items')
                ->where('order_id', $orderId)
                ->selectRaw('COALESCE(SUM(qty * price),0) as total')
                ->value('total');

            DB::table('orders')->where('id', $orderId)->update([
                'total' => (int)$sum,
                'updated_at' => now(),
            ]);
        });
    }
}
