<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Marketplace\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderService $svc) {}

    public function index(Request $r) {
        $q = Order::with('items')->when($r->filled('status'), fn($qq)=>$qq->where('status',$r->status));
        return ['success'=>true,'data'=>$q->orderByDesc('id')->paginate(20)];
    }

    public function store(Request $r) {
        $data = $r->validate([
            'items'=>'required|array|min:1',
            'items.*.product_id'=>'required|integer|exists:products,id',
            'items.*.qty'=>'required|integer|min:1',
            'currency'=>'nullable|string|size:3',
            'meta'=>'array'
        ]);
        $order = $this->svc->createOrder(optional($r->user())->id, $data['items'], $data['currency'] ?? 'IRR', $data['meta'] ?? []);
        return ['success'=>true,'data'=>$order];
    }

    public function show($id) {
        return ['success'=>true,'data'=>Order::with('items','vouchers')->findOrFail($id)];
    }

    public function pay($id) {
        $order = Order::with('items')->findOrFail($id);
        $order = $this->svc->payOrder($order);
        return ['success'=>true,'data'=>$order];
    }

    public function cancel($id) {
        $order = Order::with('items')->findOrFail($id);
        $order = $this->svc->cancelOrder($order);
        return ['success'=>true,'data'=>$order];
    }
}
