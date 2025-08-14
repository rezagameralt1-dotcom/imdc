<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SendMailJob;
use App\Mail\OrderStatusChanged;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('orders')->select('id', 'user_id', 'status', 'total', 'created_at');
        if ($user = $request->query('user_id')) {
            $q->where('user_id', (int) $user);
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        $q->orderByDesc('id');

        return ApiResponse::success($q->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);
        $id = DB::table('orders')->insertGetId([
            'user_id' => $data['user_id'],
            'status' => 'pending',
            'total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success(['id' => $id], 201);
    }

    public function addItem(Request $request, int $orderId)
    {
        $exists = DB::table('orders')->where('id', $orderId)->exists();
        if (! $exists) {
            return ApiResponse::error('Order not found', 404);
        }

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'required|integer|min:1',
            'price' => 'nullable|integer|min:0',
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $data['product_id'],
            'qty' => $data['qty'],
            'price' => $data['price'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $total = DB::table('orders')->where('id', $orderId)->value('total');

        return ApiResponse::success(['order_id' => $orderId, 'total' => $total]);
    }

    public function setStatus(Request $request, int $orderId)
    {
        $data = $request->validate([
            'status' => 'required|string|in:pending,paid,shipped,cancelled,refunded',
        ]);
        $count = DB::table('orders')->where('id', $orderId)->update([
            'status' => $data['status'],
            'updated_at' => now(),
        ]);
        if ($count === 0) {
            return ApiResponse::error('Order not found', 404);
        }

        // Enqueue email if user has email
        $userEmail = DB::table('orders')->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.id', $orderId)->value('users.email');

        if ($userEmail) {
            SendMailJob::dispatch(OrderStatusChanged::class, [$orderId, $data['status']], $userEmail)->onQueue('mail');
        }

        // Audit
        DB::table('audit_logs')->insert([
            'event' => 'order.status_changed',
            'user_id' => null,
            'payload' => json_encode(['order_id' => $orderId, 'status' => $data['status']]),
            'created_at' => now(),
        ]);

        return ApiResponse::success(['order_id' => $orderId, 'status' => $data['status']]);
    }
}
