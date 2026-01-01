<?php

namespace App\Orders\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Core\Services\ShopCustomerResolver;
use App\Orders\Http\Requests\CreateOrderRequest;
use App\Orders\Http\Requests\PayOrderRequest;
use App\Orders\Models\Order;
use App\Orders\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function __construct(
        private readonly OrderService $service,
        private readonly ShopCustomerResolver $shopCustomerResolver,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with('items')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->successResponse($query->paginate($request->integer('per_page', 15)));
    }

    public function store(CreateOrderRequest $request)
    {
        try {
            $this->authorize('create', Order::class);

            $shopCustomerId = $this->shopCustomerResolver->resolveForUserId($request->user()->id);

            $result = $this->service->create(
                $request->validated(),
                $shopCustomerId,
                $request->attributes->get('trace_id')
            );

            // Return 200 for idempotent returns, 201 for new orders
            $statusCode = $result['is_new'] ? 201 : 200;
            return $this->successResponse($result['order'], $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Check if it's an idempotency key conflict
            if ($e->errors() && isset($e->errors()['idempotency_key'])) {
                return $this->errorResponse(
                    'Idempotency key conflict',
                    409,
                    ['fields' => $e->errors()]
                );
            }
            // Return 422 for validation errors
            return $this->errorResponse(
                'Validation failed',
                422,
                ['fields' => $e->errors()]
            );
        } catch (\Exception $e) {
            // Log unexpected errors but return generic message to client
            \Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Order creation failed', 500);
        }
    }

    public function show(string $id)
    {
        $order = Order::with('items')->findOrFail($id);

        $this->authorize('view', $order);

        return $this->successResponse($order);
    }

    public function pay(PayOrderRequest $request, string $id)
    {
        $order = Order::with('items')->findOrFail($id);

        $this->authorize('update', $order);

        $paid = $this->service->markPaid($order, $request->attributes->get('trace_id'));

        return $this->successResponse($paid);
    }

    public function cancel(Request $request, string $id)
    {
        $order = Order::with('items')->findOrFail($id);

        $this->authorize('update', $order);

        $cancelled = $this->service->cancel($order);

        return $this->successResponse($cancelled);
    }
}

