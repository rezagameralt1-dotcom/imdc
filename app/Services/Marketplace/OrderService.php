<?php

namespace App\Services\Marketplace;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected InventoryService $inv,
        protected AccountingSyncService $acct
    ) {}

    public function createOrder(?int $userId, array $items, string $currency='IRR', array $meta=[]): Order {
        // $items: [ ['product_id'=>X,'qty'=>Y], ... ]
        if (empty($items)) throw ValidationException::withMessages(['items'=>'Order items required']);

        return DB::transaction(function() use ($userId,$items,$currency,$meta) {
            $order = Order::create([
                'user_id'=>$userId, 'status'=>'pending', 'currency'=>$currency,
                'subtotal'=>0,'discount_total'=>0,'tax_total'=>0,'shipping_total'=>0,'total_amount'=>0,'meta'=>$meta
            ]);

            $subtotal = 0;
            foreach ($items as $i) {
                $product = Product::findOrFail($i['product_id']);
                $qty = max((int)($i['qty'] ?? 1),1);
                $line = $product->price * $qty;
                $subtotal += $line;

                // رزرو موجودی
                $this->inv->reserve($product, $qty, 'order', (string)$order->id, $userId);

                OrderItem::create([
                    'order_id'=>$order->id,
                    'product_id'=>$product->id,
                    'qty'=>$qty,
                    'unit_price'=>$product->price,
                    'total'=>$line,
                ]);
            }

            $order->update([
                'subtotal'=>$subtotal,
                'total_amount'=> $subtotal // فعلاً بدون مالیات/حمل
            ]);

            return $order->fresh(['items']);
        });
    }

    public function payOrder(Order $order): Order {
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages(['status'=>'Only pending orders can be paid']);
        }
        return DB::transaction(function() use ($order) {
            foreach ($order->items as $it) {
                $this->inv->commitOut($it->product, $it->qty, 'order', (string)$order->id, $order->user_id);
            }
            $order->update(['status'=>'paid']);

            // سند حسابداری
            $v = $this->acct->createVoucher($order->id, 'INVOICE', (float)$order->total_amount, ['order'=>$order->id]);
            $this->acct->attemptSync($v);

            return $order->fresh(['items','vouchers']);
        });
    }

    public function cancelOrder(Order $order): Order {
        if (!in_array($order->status, ['pending'])) {
            throw ValidationException::withMessages(['status'=>'Only pending orders can be canceled']);
        }
        return DB::transaction(function() use ($order) {
            foreach ($order->items as $it) {
                $this->inv->release($it->product, $it->qty, 'order', (string)$order->id, $order->user_id);
            }
            $order->update(['status'=>'canceled']);
            return $order->fresh(['items']);
        });
    }
}
