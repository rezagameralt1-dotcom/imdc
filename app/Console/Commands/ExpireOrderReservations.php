<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Inventory\Services\InventoryService;
use App\Orders\Models\Order;
use App\Orders\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExpireOrderReservations extends Command
{
    protected $signature = 'imdc:expire-order-reservations
        {--minutes= : TTL in minutes (defaults to config imdc.orders.reservation_ttl_minutes)}
        {--limit= : Max number of orders to process per run (defaults to config imdc.orders.expire_batch_limit)}
        {--dry-run : Do not change anything, only print what would happen}';

    protected $description = 'Expire old open orders and release their inventory reservations (TTL-based).';

    public function handle(OrderService $orderService, InventoryService $inventoryService): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('imdc.orders.reservation_ttl_minutes', 15));
        $limit   = (int) ($this->option('limit') ?: config('imdc.orders.expire_batch_limit', 500));
        $dryRun  = (bool) $this->option('dry-run');

        if ($minutes <= 0) {
            $this->error('Invalid --minutes. Must be > 0.');
            return self::FAILURE;
        }
        if ($limit <= 0) {
            $this->error('Invalid --limit. Must be > 0.');
            return self::FAILURE;
        }

        $cutoff = now()->subMinutes($minutes);

        $this->info('Starting expire job...');
        $this->line('cutoff=' . $cutoff->toDateTimeString() . ' minutes=' . $minutes . ' limit=' . $limit . ' dry_run=' . ($dryRun ? 'true' : 'false'));

        // 1) candidate order_ids from inventory DB that still have reserved reservations
        try {
            $candidateOrderIds = DB::connection('inventory')
                ->table('inventory_reservations')
                ->where('status', 'reserved')
                ->distinct()
                ->orderBy('order_id')
                ->limit($limit * 5) // guard; candidates may include non-expired orders, we filter later
                ->pluck('order_id')
                ->values()
                ->all();
        } catch (Throwable $e) {
            $this->warn('Inventory database unavailable: ' . $e->getMessage());
            $this->warn('Skipping expiration job. Ensure inventory DB is accessible.');
            return self::SUCCESS; // Exit gracefully, don't crash loop
        }

        if (empty($candidateOrderIds)) {
            $this->info('No reserved inventory reservations found. Nothing to do.');
            return self::SUCCESS;
        }

        $expiredStatuses = [Order::STATUS_PENDING, Order::STATUS_RESERVED];

        // 2) Filter orders that are open + older than cutoff
        $orders = Order::query()
            ->with('items')
            ->whereIn('id', $candidateOrderIds)
            ->whereIn('status', $expiredStatuses)
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $this->line('expired_open_orders_found=' . $orders->count());

        $stats = [
            'canceled' => 0,
            'released' => 0,
            'skipped_no_reserved' => 0,
            'cancel_failed' => 0,
            'release_failed' => 0,
        ];

        foreach ($orders as $order) {
            $orderId = (string) $order->id;

            // Double-check there are still reserved reservations for this order (idempotency / race-safe)
            try {
                $stillReserved = (int) DB::connection('inventory')
                    ->table('inventory_reservations')
                    ->where('order_id', $orderId)
                    ->where('status', 'reserved')
                    ->count();
            } catch (Throwable $e) {
                $this->warn("SKIP order_id={$orderId} reason=inventory_db_unavailable: " . $e->getMessage());
                $stats['skipped_no_reserved']++;
                continue;
            }

            if ($stillReserved <= 0) {
                $stats['skipped_no_reserved']++;
                $this->line("SKIP order_id={$orderId} reason=no_reserved_reservations");
                continue;
            }

            $items = $order->items->map(fn($it) => [
                'product_id' => (string) $it->product_id,
                'quantity'   => (int) $it->quantity,
            ])->toArray();

            $this->line("EXPIRE order_id={$orderId} status={$order->status} created_at={$order->created_at} reserved_rows={$stillReserved}");

            if ($dryRun) {
                continue;
            }

            // cancel via service (contains domain rules)
            try {
                $orderService->cancel($order);
                $stats['canceled']++;
            } catch (Throwable $e) {
                $stats['cancel_failed']++;
                $this->error("CANCEL_FAIL order_id={$orderId} err=" . $e->getMessage());
                continue;
            }

            // release inventory reservations
            try {
                $inventoryService->releaseForOrder($orderId, $items, (string) $order->trace_id);
                $stats['released']++;
            } catch (Throwable $e) {
                $stats['release_failed']++;
                $this->error("RELEASE_FAIL order_id={$orderId} err=" . $e->getMessage());
            }
        }

        $this->info('Done.');
        foreach ($stats as $k => $v) {
            $this->line($k . '=' . $v);
        }

        return self::SUCCESS;
    }
}
